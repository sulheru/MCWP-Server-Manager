#!/usr/bin/env bash
#
# optigrid-s1-1-crear-estructura-plugin.sh
#
# Crea la estructura base del plugin MC Manager Subscriptions.
# Puede ejecutarse múltiples veces sin producir errores.
#

set -euo pipefail

PLUGIN_ROOT="$HOME/david/web_data/wp-content/plugins"
PLUGIN_NAME="mc-manager-subscriptions"
PLUGIN_DIR="$PLUGIN_ROOT/$PLUGIN_NAME"

echo
echo "==> Comprobando directorio de plugins"

if [[ ! -d "$PLUGIN_ROOT" ]]; then
    echo "[FAIL] No existe: $PLUGIN_ROOT"
    exit 1
fi

echo "[PASS] Directorio encontrado"

echo
echo "==> Creando estructura"

mkdir -p \
"$PLUGIN_DIR/src/Core" \
"$PLUGIN_DIR/src/Admin" \
"$PLUGIN_DIR/src/Domain/Plans" \
"$PLUGIN_DIR/src/Domain/Subscriptions" \
"$PLUGIN_DIR/src/Domain/Payments" \
"$PLUGIN_DIR/src/Domain/Entitlements" \
"$PLUGIN_DIR/src/Gateways" \
"$PLUGIN_DIR/src/Integration" \
"$PLUGIN_DIR/templates/admin" \
"$PLUGIN_DIR/assets/css" \
"$PLUGIN_DIR/assets/js" \
"$PLUGIN_DIR/docs"

echo "[PASS] Directorios creados"

echo
echo "==> Creando archivos"

touch \
"$PLUGIN_DIR/mc-manager-subscriptions.php" \
"$PLUGIN_DIR/uninstall.php" \
"$PLUGIN_DIR/src/Core/Plugin.php" \
"$PLUGIN_DIR/src/Core/Activator.php" \
"$PLUGIN_DIR/src/Core/Database.php" \
"$PLUGIN_DIR/src/Admin/AdminMenu.php" \
"$PLUGIN_DIR/src/Admin/AdminPage.php" \
"$PLUGIN_DIR/src/Gateways/PaymentGatewayInterface.php" \
"$PLUGIN_DIR/src/Gateways/SandboxGateway.php" \
"$PLUGIN_DIR/src/Integration/McManagerUsersBridge.php" \
"$PLUGIN_DIR/templates/admin/dashboard.php" \
"$PLUGIN_DIR/assets/css/admin.css" \
"$PLUGIN_DIR/assets/js/admin.js" \
"$PLUGIN_DIR/docs/README.md"

echo "[PASS] Archivos creados"

echo
echo "==> Añadiendo index.php de protección"

find "$PLUGIN_DIR" -type d | while read -r dir
do
    file="$dir/index.php"

    if [[ ! -f "$file" ]]; then
        cat > "$file" <<'PHP'
<?php
// Silence is golden.
PHP
    fi
done

echo "[PASS] index.php creados"

echo
echo "==> Resumen"

find "$PLUGIN_DIR" | sort

echo
echo "[PASS] Plugin inicializado correctamente"
