#!/usr/bin/env bash
set -Eeuo pipefail

# Diagnóstico de discrepancias entre Git local y GitHub.
# Solo lectura: no modifica el repositorio.
#
# Uso:
#   bash optigrid-git-sync-diagnostic.sh
#   bash optigrid-git-sync-diagnostic.sh /ruta/al/repositorio
#
# Salida:
#   optigrid-git-sync-diagnostic-YYYYmmdd-HHMMSS.txt

TARGET="${1:-.}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUTPUT="${PWD}/optigrid-git-sync-diagnostic-${TIMESTAMP}.txt"

section() {
    printf '\n\n============================================================\n'
    printf '%s\n' "$1"
    printf '============================================================\n'
}

run() {
    printf '\n$ %q ' "$1"
    shift || true
    printf '%q ' "$@"
    printf '\n'
    "$@" 2>&1 || true
}

{
    section "1. CONTEXTO"
    printf 'Fecha: %s\n' "$(date --iso-8601=seconds)"
    printf 'Directorio solicitado: %s\n' "$TARGET"
    printf 'PWD inicial: %s\n' "$PWD"
    printf 'Usuario: %s\n' "$(id -un)"
    printf 'Host: %s\n' "$(hostname)"

    cd "$TARGET"

    section "2. RAÍZ REAL DEL REPOSITORIO"
    git rev-parse --is-inside-work-tree 2>&1 || {
        printf '\n[ERROR] El directorio no está dentro de un repositorio Git.\n'
        exit 1
    }

    REPO_ROOT="$(git rev-parse --show-toplevel)"
    GIT_DIR="$(git rev-parse --git-dir)"
    cd "$REPO_ROOT"

    printf 'Raíz Git: %s\n' "$REPO_ROOT"
    printf 'Git dir: %s\n' "$GIT_DIR"
    printf 'Ruta física: %s\n' "$(pwd -P)"

    section "3. ESTADO Y SINCRONIZACIÓN"
    git status --short --branch
    printf '\nHEAD local:\n'
    git log -1 --oneline --decorate
    printf '\nRamas:\n'
    git branch -vv
    printf '\nRemotos:\n'
    git remote -v

    printf '\nConfiguración de upstream:\n'
    CURRENT_BRANCH="$(git symbolic-ref --quiet --short HEAD || true)"
    printf 'Rama actual: %s\n' "${CURRENT_BRANCH:-DETACHED_HEAD}"
    if [[ -n "$CURRENT_BRANCH" ]]; then
        UPSTREAM="$(git rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' 2>/dev/null || true)"
        printf 'Upstream: %s\n' "${UPSTREAM:-SIN_UPSTREAM}"
        if [[ -n "$UPSTREAM" ]]; then
            printf 'Diferencia local/remoto (behind ahead): '
            git rev-list --left-right --count "${UPSTREAM}...HEAD" || true
        fi
    fi

    section "4. CONFIGURACIÓN QUE PUEDE OCULTAR ARCHIVOS"
    printf '\nstatus.showUntrackedFiles:\n'
    git config --show-origin --get-all status.showUntrackedFiles || printf '(no definido; valor normal = all)\n'

    printf '\ncore.excludesFile:\n'
    git config --show-origin --get-all core.excludesFile || printf '(no definido)\n'

    printf '\nSparse checkout:\n'
    git config --show-origin --get core.sparseCheckout || printf 'false/no definido\n'
    git sparse-checkout list 2>&1 || true

    printf '\n.git/info/exclude:\n'
    if [[ -f "$(git rev-parse --git-path info/exclude)" ]]; then
        sed -n '1,240p' "$(git rev-parse --git-path info/exclude)"
    else
        printf '(no existe)\n'
    fi

    section "5. BÚSQUEDA DE GATEWAY Y WORKER"
    printf '\nDirectorios y archivos coincidentes dentro de la raíz:\n'
    find "$REPO_ROOT" \
        \( -iname '*gateway*' -o -iname '*worker*' -o -iname '*sync-worker*' -o -iname '*sync_worker*' \) \
        -print 2>/dev/null | sort

    printf '\nArchivos versionados coincidentes:\n'
    git ls-files | grep -Ei '(^|/)([^/]*gateway[^/]*|[^/]*worker[^/]*)($|/)' || printf '(ninguno)\n'

    printf '\nArchivos no versionados, incluidos los ignorados:\n'
    git status --short --untracked-files=all --ignored | \
        grep -Ei 'gateway|worker|sync[-_]?worker' || printf '(ninguno)\n'

    section "6. MOTIVO DE IGNORADO"
    mapfile -t CANDIDATES < <(
        find "$REPO_ROOT" \
            \( -iname '*gateway*' -o -iname '*worker*' -o -iname '*sync-worker*' -o -iname '*sync_worker*' \) \
            -print 2>/dev/null | sort
    )

    if [[ ${#CANDIDATES[@]} -eq 0 ]]; then
        printf 'No se encontraron rutas gateway/worker dentro de la raíz Git.\n'
    else
        for path in "${CANDIDATES[@]}"; do
            rel="${path#"$REPO_ROOT"/}"
            printf '\nRuta: %s\n' "$rel"
            printf 'Tipo: '
            [[ -d "$path" ]] && printf 'directorio\n' || printf 'archivo\n'

            if git ls-files --error-unmatch -- "$rel" >/dev/null 2>&1; then
                printf 'Estado: VERSIONADO\n'
            elif git check-ignore -q -- "$rel" 2>/dev/null; then
                printf 'Estado: IGNORADO\n'
                git check-ignore -v -- "$rel" || true
            else
                printf 'Estado: NO VERSIONADO Y NO IGNORADO\n'
            fi
        done
    fi

    section "7. REPOSITORIOS GIT ANIDADOS"
    printf '\nDirectorios .git encontrados bajo la raíz:\n'
    find "$REPO_ROOT" -mindepth 2 \
        \( -type d -o -type f \) -name .git -print 2>/dev/null | sort || true

    printf '\nSubmódulos declarados:\n'
    if [[ -f .gitmodules ]]; then
        cat .gitmodules
        printf '\nEstado de submódulos:\n'
        git submodule status --recursive || true
    else
        printf '(no hay .gitmodules)\n'
    fi

    section "8. ARCHIVOS IGNORADOS RELEVANTES"
    printf '\nReglas .gitignore que mencionan servicios, Python o directorios amplios:\n'
    find "$REPO_ROOT" -name .gitignore -type f -print0 2>/dev/null |
    while IFS= read -r -d '' ignore; do
        matches="$(grep -nEi 'gateway|worker|service|python|src|app|docker|compose|^\*|^/[^#]+/$' "$ignore" || true)"
        if [[ -n "$matches" ]]; then
            printf '\n--- %s ---\n' "${ignore#"$REPO_ROOT"/}"
            printf '%s\n' "$matches"
        fi
    done

    section "9. CONTENIDO DEL COMMIT LOCAL Y REMOTO"
    printf '\nÁrbol de HEAD local, nivel 4, filtrado:\n'
    git ls-tree -r --name-only HEAD | \
        grep -Ei 'gateway|worker|sync[-_]?worker|compose|service' || printf '(sin coincidencias)\n'

    if [[ -n "${UPSTREAM:-}" ]]; then
        printf '\nÁrbol del upstream, filtrado:\n'
        git ls-tree -r --name-only "$UPSTREAM" | \
            grep -Ei 'gateway|worker|sync[-_]?worker|compose|service' || printf '(sin coincidencias)\n'

        printf '\nDiferencias de nombres entre HEAD y upstream:\n'
        git diff --name-status "$UPSTREAM"...HEAD || true
    fi

    section "10. DIRECTORIOS HERMANOS FUERA DEL REPOSITORIO"
    PARENT="$(dirname "$REPO_ROOT")"
    printf 'Padre de la raíz: %s\n' "$PARENT"
    find "$PARENT" -maxdepth 3 \
        \( -iname '*gateway*' -o -iname '*worker*' -o -iname '*sync-worker*' -o -iname '*sync_worker*' \) \
        -print 2>/dev/null | sort || true

    section "11. DIAGNÓSTICO ORIENTATIVO"
    printf '%s\n' \
'Interpreta los resultados así:

A) Las rutas aparecen como IGNORADAS:
   Git no las mostrará en un status normal. Revisa la regla indicada por
   "git check-ignore -v".

B) Las rutas están fuera de la "Raíz Git":
   El repositorio local que estás consultando no contiene físicamente esos
   servicios. Git solo controla lo que está bajo su raíz.

C) Existe un .git anidado:
   Gateway o worker pueden ser repositorios independientes. El repositorio
   padre no versionará su contenido normal.

D) Son submódulos:
   GitHub mostrará un enlace al commit del submódulo, no los archivos como
   contenido normal del repositorio padre.

E) "git ls-files" no los muestra:
   Nunca fueron añadidos al índice de este repositorio, aunque estén en disco.

F) HEAD y upstream coinciden pero faltan en ambos:
   "Todo sincronizado" solo significa que los commits locales coinciden con
   la rama remota. No significa que todos los archivos del servidor estén
   versionados.

G) La rama o el remoto no son los esperados:
   Puedes estar sincronizado con otra rama, otro fork o incluso otro repositorio.'
} | tee "$OUTPUT"

printf '\n[PASS] Informe creado: %s\n' "$OUTPUT"
