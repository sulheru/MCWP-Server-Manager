# OptiGrid Minecraft Manager

OptiGrid Minecraft Manager is an open and modular platform for managing
Minecraft servers through WordPress.

The project combines WordPress plugins, backend services and automation
workers into a unified management platform capable of handling player
identity, authentication, server access, synchronization and future
extensions.

The architecture follows a service-oriented design where infrastructure,
business logic and game server remain fully decoupled, allowing every
component to be deployed and scaled independently.

---

## Features

- WordPress administration interface
- Microsoft Entra ID authentication
- Minecraft account verification
- Server access management
- Whitelist and blacklist synchronization
- Background workers
- REST Gateway
- Secure RCON communication
- WireGuard private networking
- Docker-first architecture

---

## Architecture

```
                +-----------------------+
                |      WordPress        |
                |  Administration UI    |
                +-----------+-----------+
                            |
                     Internal REST API
                            |
                +-----------v-----------+
                |     Gateway API       |
                +-----------+-----------+
                            |
                         RCON over VPN
                            |
                +-----------v-----------+
                |      PaperMC          |
                |   Minecraft Server    |
                +-----------------------+

                +-----------------------+
                |     Sync Worker       |
                | Background Automation |
                +-----------------------+
```

---

## Repository Structure

```
.
├── docs/
├── gateway/
├── sync-worker/
└── wordpress/
    └── wp-content/
        └── plugins/
            ├── mc-manager-users/
            ├── mc-manager-azure-entra-id/
            └── ...
```

---

## Design Principles

- One VPS, one responsibility.
- One container, one service.
- Infrastructure contains no business logic.
- Secure-by-default networking.
- Docker-native deployment.
- Modular and extensible architecture.
- WordPress as the management platform.
- Minecraft as an independent service.

---

## Example Deployment

The reference deployment consists of two independent VPS instances.

**Web Platform**

- WordPress
- MySQL
- Gateway API
- Sync Worker

**Minecraft Platform**

- PaperMC
- Private RCON endpoint

Both systems communicate exclusively through a WireGuard VPN.

---

## Use Cases

OptiGrid Minecraft Manager can be used for:

- Community servers
- Educational environments
- Private networks
- Membership-based servers
- Enterprise Minecraft deployments
- Charity projects
- Multi-server infrastructures

The first production deployment powers a charity Minecraft server whose
revenues are donated to a children's cancer foundation, demonstrating the
platform in a real-world scenario.

---

## License

License to be defined.
