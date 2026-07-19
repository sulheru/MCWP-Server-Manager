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
