#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ARCHIVE_DIR="${PROJECT_ROOT}/.local-archive"
DOCS_DIR="${PROJECT_ROOT}/docs"
PLUGIN_ROOT="${PROJECT_ROOT}/wordpress/wp-content/plugins"

AZURE_PLUGIN="${PLUGIN_ROOT}/mc-manager-azure-entra-id"
USERS_PLUGIN="${PLUGIN_ROOT}/mc-manager-users"

TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"
ARCHIVE_RUN="${ARCHIVE_DIR}/${TIMESTAMP}"

log() {
    printf '\033[1;34m[INFO]\033[0m %s\n' "$*"
}

ok() {
    printf '\033[1;32m[OK]\033[0m %s\n' "$*"
}

warn() {
    printf '\033[1;33m[AVISO]\033[0m %s\n' "$*"
}

fail() {
    printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2
    exit 1
}

move_to_archive() {
    local file="$1"
    local relative destination

    [[ -e "$file" ]] || return 0

    relative="${file#${PROJECT_ROOT}/}"
    destination="${ARCHIVE_RUN}/${relative}"

    mkdir -p "$(dirname "$destination")"
    mv "$file" "$destination"

    log "Archivado: ${relative}"
}

require_project_structure() {
    [[ -d "$PLUGIN_ROOT" ]] || \
        fail "No se encuentra ${PLUGIN_ROOT}. Ejecuta el script desde la raíz del proyecto."

    [[ -d "$AZURE_PLUGIN" ]] || \
        fail "No se encuentra el plugin mc-manager-azure-entra-id."

    [[ -d "$USERS_PLUGIN" ]] || \
        fail "No se encuentra el plugin mc-manager-users."
}

create_directories() {
    mkdir -p \
        "$DOCS_DIR/architecture" \
        "$DOCS_DIR/diagrams" \
        "$PROJECT_ROOT/gateway" \
        "$PROJECT_ROOT/sync-worker" \
        "$ARCHIVE_RUN"

    ok "Directorios principales preparados."
}

archive_development_files() {
    log "Moviendo copias, parches y archivos de diagnóstico..."

    while IFS= read -r -d '' file; do
        move_to_archive "$file"
    done < <(
        find "$AZURE_PLUGIN" -type f \
            \( \
                -name '*.backup*' \
                -o -name '*.bak' \
                -o -name '*.old' \
                -o -name 'patch_*.sh' \
                -o -name 'inspect_*.sh' \
                -o -name '*_inspection_*.txt' \
                -o -name 'tools-test-*.php' \
            \) \
            -print0
    )

    ok "Archivos de desarrollo archivados en .local-archive/${TIMESTAMP}/"
}

create_gitignore() {
    cat > "${PROJECT_ROOT}/.gitignore" <<'EOF'
# Secretos y configuración local
.env
.env.*
!.env.example
wp-config.php

# Archivos locales archivados por el script
.local-archive/

# Copias de seguridad
*.bak
*.backup
*.backup-*
*.backup.*
*.old
*~

# Parches y diagnósticos locales
patch_*.sh
inspect_*.sh
*_inspection_*.txt
tools-test-*.php

# Registros y temporales
*.log
*.tmp
*.swp
*.swo

# Python
__pycache__/
*.py[cod]
.pytest_cache/
.mypy_cache/
.venv/
venv/

# WordPress generado o persistente
wordpress/wp-content/uploads/
wordpress/wp-content/cache/
wordpress/wp-content/upgrade/
wordpress/wp-content/debug.log

# Dependencias
vendor/
node_modules/

# IDE y sistema operativo
.vscode/
.idea/
.DS_Store
Thumbs.db

# Docker local
docker-compose.override.yml
compose.override.yml
EOF

    ok ".gitignore creado."
}

create_readme() {
    local readme="${PROJECT_ROOT}/README.md"

    if [[ -f "$readme" ]]; then
        cp "$readme" "${ARCHIVE_RUN}/README.md.before-restructure"
        warn "El README anterior se ha conservado en el archivo local."
    fi

    cat > "$readme" <<'EOF'
# OptiGrid ONG Minecraft

Plataforma para gestionar un servidor solidario de Minecraft conectado a
WordPress, Microsoft Entra ID y servicios internos de sincronización.

## Componentes

```text
WordPress
├── mc-manager-azure-entra-id
└── mc-manager-users

Gateway
└── API interna para operaciones sobre Minecraft mediante RCON

Sync Worker
└── Sincronización periódica de usuarios, permisos y estados

Minecraft
└── PaperMC desplegado en una VPS independiente
```

## Estructura del repositorio

```text
.
├── docs/
│   ├── architecture/
│   └── diagrams/
├── gateway/
├── sync-worker/
└── wordpress/
    └── wp-content/
        └── plugins/
            ├── mc-manager-azure-entra-id/
            └── mc-manager-users/
```

## Arquitectura

WordPress, la base de datos, el gateway y el worker se ejecutan en la VPS web.

PaperMC se ejecuta en una VPS independiente.

La comunicación administrativa entre ambas VPS se realiza mediante una red
privada WireGuard. RCON no debe exponerse públicamente.

## Estado

Versión inicial del MVP:

```text
v0.1.0
```

## Seguridad

No deben almacenarse en el repositorio:

- Contraseñas.
- Tokens OAuth.
- Claves de Microsoft Entra ID.
- Claves de WireGuard.
- Credenciales de MySQL.
- Contraseñas RCON.
- Archivos `.env`.
- Copias de seguridad con datos reales.

## Licencia

Pendiente de definir.
EOF

    ok "README.md creado."
}

create_env_examples() {
    if [[ ! -f "${PROJECT_ROOT}/gateway/.env.example" ]]; then
        cat > "${PROJECT_ROOT}/gateway/.env.example" <<'EOF'
APP_ENV=development
APP_HOST=0.0.0.0
APP_PORT=8000

RCON_HOST=10.77.0.2
RCON_PORT=25575
RCON_PASSWORD=change-me
EOF
    fi

    if [[ ! -f "${PROJECT_ROOT}/sync-worker/.env.example" ]]; then
        cat > "${PROJECT_ROOT}/sync-worker/.env.example" <<'EOF'
POLL_SECONDS=10
DRY_RUN=true

GATEWAY_URL=http://gateway:8000

DB_HOST=db
DB_PORT=3306
DB_NAME=wordpress
DB_USER=wordpress
DB_PASSWORD=change-me
EOF
    fi

    ok "Archivos .env.example preparados."
}

create_docs_index() {
    cat > "${DOCS_DIR}/README.md" <<'EOF'
# Documentación

## Directorios

- `architecture/`: documentos de arquitectura y decisiones técnicas.
- `diagrams/`: diagramas de red, servicios y flujos de comunicación.

Los documentos pueden mantenerse en Markdown y, cuando sea necesario,
publicarse también en PDF.
EOF

    ok "Índice de documentación creado."
}

create_placeholder_files() {
    local directory

    for directory in \
        "$PROJECT_ROOT/gateway" \
        "$PROJECT_ROOT/sync-worker" \
        "$DOCS_DIR/architecture" \
        "$DOCS_DIR/diagrams"
    do
        if [[ -z "$(find "$directory" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
            touch "${directory}/.gitkeep"
        fi
    done

    ok "Directorios vacíos preparados para Git."
}

check_sensitive_files() {
    local matches

    log "Buscando nombres de archivos potencialmente sensibles..."

    matches="$(
        find "$PROJECT_ROOT" \
            -path "${ARCHIVE_DIR}" -prune -o \
            -type f \
            \( \
                -iname '*secret*' \
                -o -iname '*credential*' \
                -o -iname '*private*key*' \
                -o -iname 'wp-config.php' \
                -o -iname '.env' \
            \) \
            -print
    )"

    if [[ -n "$matches" ]]; then
        warn "Revisa estos archivos antes de ejecutar git add:"
        printf '%s\n' "$matches"
    else
        ok "No se encontraron nombres de archivos evidentemente sensibles."
    fi
}

show_result() {
    printf '\n'
    ok "Reestructuración terminada."
    printf '\nEstructura resultante:\n\n'

    if command -v tree >/dev/null 2>&1; then
        tree -a -L 6 \
            -I '.git|.local-archive|uploads|cache|node_modules|vendor' \
            "$PROJECT_ROOT"
    else
        find "$PROJECT_ROOT" \
            -path "${ARCHIVE_DIR}" -prune -o \
            -path "${PROJECT_ROOT}/.git" -prune -o \
            -maxdepth 6 \
            -print
    fi

    printf '\nArchivos antiguos conservados en:\n%s\n' "$ARCHIVE_RUN"

    printf '\nSiguientes comandos recomendados:\n\n'
    printf '  cd %q\n' "$PROJECT_ROOT"
    printf '  git init\n'
    printf '  git status\n'
    printf '  git add .\n'
    printf '  git status\n'
    printf '  git commit -m "Initial OptiGrid MVP v0.1.0"\n'
    printf '  git branch -M main\n'
}

main() {
    log "Reestructurando OptiGrid ONG Minecraft..."
    require_project_structure
    create_directories
    archive_development_files
    create_gitignore
    create_readme
    create_env_examples
    create_docs_index
    create_placeholder_files
    check_sensitive_files
    show_result
}

main "$@"
