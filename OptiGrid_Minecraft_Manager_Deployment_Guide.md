# OptiGrid Minecraft Manager

## Deployment & Configuration Guide

Version: v0.1.0

------------------------------------------------------------------------

# 1. Introduction

This document describes the recommended deployment model,
infrastructure, network topology and operational requirements for
**OptiGrid Minecraft Manager**.

OptiGrid is a modular management platform for Minecraft servers built
around WordPress plugins and backend services. It is designed to
separate infrastructure from business logic while providing a scalable
and secure architecture.

------------------------------------------------------------------------

# 2. Required Knowledge

The following skills are recommended before deploying the platform:

-   Linux system administration
-   Docker & Docker Compose
-   WordPress administration
-   MySQL
-   Basic networking
-   WireGuard VPN
-   Minecraft server administration
-   Basic Python (optional)

------------------------------------------------------------------------

# 3. Recommended Infrastructure

Production deployment is based on two independent VPS instances.

## Web Platform VPS

Responsibilities:

-   WordPress
-   MySQL
-   Gateway API
-   Sync Worker

## Minecraft VPS

Responsibilities:

-   PaperMC Server
-   RCON endpoint
-   Minecraft world data

This separation improves security, scalability and maintenance.

------------------------------------------------------------------------

# 4. Container Topology

## Web VPS

-   WordPress
-   MySQL
-   Gateway API
-   Sync Worker

## Minecraft VPS

-   PaperMC

Each container has a single responsibility.

------------------------------------------------------------------------

# 5. Network Topology

``` text
                Internet
                    │
      ┌─────────────┴─────────────┐
      │                           │
      ▼                           ▼
  Web Platform VPS          Minecraft VPS
      │                           │
 Docker Network             Docker Network
      │                           │
 WordPress                PaperMC Server
 MySQL                    RCON
 Gateway API
 Sync Worker

        WireGuard Private VPN
```

All administrative traffic travels exclusively through the VPN.

------------------------------------------------------------------------

# 6. Security Principles

-   Infrastructure contains no business logic.
-   Services are isolated using Docker.
-   RCON is never exposed to the Internet.
-   MySQL is accessible only from the Docker network.
-   Gateway API is private.
-   Communication between VPS instances uses WireGuard.
-   Default-deny firewall policy is recommended.

------------------------------------------------------------------------

# 7. Required Ports

  Port        Purpose     Exposure
  ----------- ----------- -----------
  80          HTTP        Public
  443         HTTPS       Public
  25565       Minecraft   Public
  25575       RCON        VPN Only
  51820/UDP   WireGuard   Peer Only
  3306        MySQL       Internal

------------------------------------------------------------------------

# 8. Repository Structure

``` text
docs/
gateway/
sync-worker/
wordpress/
```

The repository should remain modular, allowing every component to evolve
independently.

------------------------------------------------------------------------

# 9. Development Philosophy

Core principles:

-   One VPS = One responsibility.
-   One container = One service.
-   Infrastructure should remain generic.
-   Business logic belongs to the services.
-   Components should be independently deployable.
-   Security by default.
-   Docker-first architecture.

------------------------------------------------------------------------

# 10. Typical Deployment Workflow

1.  Provision both VPS instances.
2.  Configure firewall rules.
3.  Install Docker and Docker Compose.
4.  Configure WireGuard.
5.  Deploy containers.
6.  Configure WordPress.
7.  Configure Minecraft.
8.  Verify Gateway connectivity.
9.  Verify Sync Worker.
10. Enable production services.

------------------------------------------------------------------------

# 11. Future Components

The platform has been designed to support additional modules such as:

-   Dashboard
-   Metrics
-   Blacklist management
-   Ban management
-   Subscription management
-   Payment integration
-   Cosmetic rewards
-   Multi-server deployments
-   Monitoring
-   Grafana
-   Prometheus

------------------------------------------------------------------------

# 12. Design Goal

OptiGrid Minecraft Manager is intended to be a reusable platform rather
than a single Minecraft server.

The first production deployment powers a charity Minecraft server, but
the platform itself is designed to support community servers,
educational environments, commercial deployments and any project
requiring centralized Minecraft server management through WordPress.
