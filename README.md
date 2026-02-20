# Docker Hosts File Sync

[![CI](https://github.com/WebProject-xyz/docker-hosts-file-sync/actions/workflows/ci.yml/badge.svg)](https://github.com/WebProject-xyz/docker-hosts-file-sync/actions/workflows/ci.yml)
[![Release](https://github.com/WebProject-xyz/docker-hosts-file-sync/actions/workflows/release.yml/badge.svg)](https://github.com/WebProject-xyz/docker-hosts-file-sync/actions/workflows/release.yml)
[![Docker Image](https://github.com/WebProject-xyz/docker-hosts-file-sync/actions/workflows/build-docker-image.yml/badge.svg)](https://github.com/WebProject-xyz/docker-hosts-file-sync/actions/workflows/build-docker-image.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/webproject-xyz/docker-hostsfile-sync)](https://packagist.org/packages/webproject-xyz/docker-hostsfile-sync)
[![Latest Stable Version](https://img.shields.io/packagist/v/webproject-xyz/docker-hostsfile-sync)](https://packagist.org/packages/webproject-xyz/docker-hostsfile-sync)
[![Total Downloads](https://img.shields.io/packagist/dt/webproject-xyz/docker-hostsfile-sync)](https://packagist.org/packages/webproject-xyz/docker-hostsfile-sync)
[![License](https://img.shields.io/packagist/l/webproject-xyz/docker-hostsfile-sync)](https://packagist.org/packages/webproject-xyz/docker-hostsfile-sync)

Automatically sync your `/etc/hosts` file with running Docker containers. Each container gets hostnames for every network it's connected to.

> Inspired by [docker-hostmanager](https://github.com/iamluc/docker-hostmanager)

## Features

- Listens to Docker events in real-time (start, stop, restart)
- Generates hostnames based on container name and network
- Supports reverse proxy setups (nginx-proxy, Traefik, etc.)
- Reads hostnames from `DOMAIN_NAME` and `VIRTUAL_HOST` environment variables
- Works with Docker and Podman

## Quick Start

### Using Docker (Recommended)

```bash
docker run -d \
  --name docker-hostfile-sync \
  --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /etc/hosts:/app/hosts \
  ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
```

### Using Docker Compose

```yaml
services:
  docker-hostfile-sync:
    image: ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
    container_name: docker-hostfile-sync
    restart: always
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /etc/hosts:/app/hosts
```

```bash
docker compose up -d
```

## Configuration

### Command Line Options

| Option                     | Short | Description                       | Default       |
|----------------------------|-------|-----------------------------------|---------------|
| `--hosts_file`             | `-f`  | Path to hosts file                | `./tmp-hosts` |
| `--tld`                    | `-t`  | TLD suffix for hostnames          | `.docker`     |
| `--reverse-proxy-host-ip`  | `-r`  | IP address of your reverse proxy  | -             |

### Environment Variables

You can also configure via environment variables:

| Variable     | Description                      |
|--------------|----------------------------------|
| `HOSTS_FILE` | Path to hosts file               |
| `TLD`        | TLD suffix for hostnames         |

## Examples

### Example 1: Basic Setup

A simple web application with a database:

```yaml
# docker-compose.yml
services:
  docker-hostfile-sync:
    image: ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
    restart: always
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /etc/hosts:/app/hosts

  webapp:
    image: nginx:alpine
    container_name: webapp

  database:
    image: mysql:8
    container_name: database
```

**Generated hosts entries:**

```text
## docker-hostsfile-sync
172.20.0.2 webapp.docker webapp.myproject_default
172.20.0.3 database.docker database.myproject_default
## docker-hostsfile-sync-end
```

Now you can access your services:
- `http://webapp.docker` → nginx container
- `mysql -h database.docker` → MySQL container

---

### Example 2: Multiple Networks

When containers are connected to multiple networks, each network gets its own hostname:

```yaml
# docker-compose.yml
services:
  docker-hostfile-sync:
    image: ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
    restart: always
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /etc/hosts:/app/hosts

  webapp:
    image: nginx:alpine
    container_name: webapp
    networks:
      - frontend
      - backend

  api:
    image: node:alpine
    container_name: api
    networks:
      - backend

  database:
    image: postgres:16
    container_name: database
    networks:
      - backend

networks:
  frontend:
  backend:
```

**Generated hosts entries:**

```text
## docker-hostsfile-sync
172.21.0.2 webapp.docker webapp.frontend
172.22.0.2 webapp.backend
172.22.0.3 api.docker api.backend
172.22.0.4 database.docker database.backend
## docker-hostsfile-sync-end
```

This allows:
- Frontend services reach `webapp` via `webapp.frontend`
- Backend services communicate via `*.backend` hostnames
- Your host machine can reach any container directly

---

### Example 3: With Reverse Proxy (nginx-proxy)

For production-like setups with a reverse proxy, use the `--reverse-proxy-host-ip` option. This routes URL-like hostnames (containing dots) to your proxy:

```yaml
# docker-compose.yml
services:
  docker-hostfile-sync:
    image: ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
    restart: always
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /etc/hosts:/app/hosts
    command: --reverse-proxy-host-ip=172.16.238.100

  nginx-proxy:
    image: nginxproxy/nginx-proxy
    container_name: nginx-proxy
    ports:
      - "80:80"
    volumes:
      - /var/run/docker.sock:/tmp/docker.sock:ro
    networks:
      proxy:
        ipv4_address: 172.16.238.100

  webapp:
    image: nginx:alpine
    container_name: webapp
    environment:
      - VIRTUAL_HOST=webapp.local,www.webapp.local
    networks:
      - proxy
      - default

  api:
    image: node:alpine
    container_name: api
    environment:
      - DOMAIN_NAME=api.local
    networks:
      proxy:
        aliases:
          - api.dev.local
      default:

networks:
  proxy:
    ipam:
      config:
        - subnet: 172.16.238.0/24
```

**Generated hosts entries:**

```text
## docker-hostsfile-sync
# nginx-proxy container
172.16.238.100 nginx-proxy.docker nginx-proxy.proxy

# webapp container - direct access via container IP
172.20.0.2 webapp.docker webapp.myproject_default
172.16.238.2 webapp.proxy

# webapp container - reverse proxy routes (from VIRTUAL_HOST env var)
172.16.238.100 webapp.local www.webapp.local

# api container - direct access
172.20.0.3 api.docker api.myproject_default
172.16.238.3 api.proxy

# api container - reverse proxy routes (from DOMAIN_NAME env var and network alias)
172.16.238.100 api.dev.local api.local
## docker-hostsfile-sync-end
```

Now you can:
- Access `http://webapp.local` → routed through nginx-proxy to webapp
- Access `http://api.local` → routed through nginx-proxy to api
- Access `http://webapp.proxy` → direct to webapp container (bypassing proxy)
- Debug with `http://api.docker` → direct container access

---

### Example 4: Network Aliases

Network aliases let you give containers additional hostnames. Aliases containing a dot (`.`) are treated as URLs and routed to the reverse proxy:

```yaml
services:
  docker-hostfile-sync:
    image: ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
    restart: always
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /etc/hosts:/app/hosts
    command: --reverse-proxy-host-ip=172.16.238.100

  webapp:
    image: nginx:alpine
    container_name: webapp
    networks:
      default:
        aliases:
          - frontend          # Simple alias → webapp IP
          - app.example.com   # URL-like alias → reverse proxy IP
```

**Generated hosts entries:**

```text
## docker-hostsfile-sync
172.20.0.2 webapp.docker frontend.default webapp.default
172.16.238.100 app.example.com
## docker-hostsfile-sync-end
```

## How It Works

The tool manages a section in your hosts file between these markers:

```text
## docker-hostsfile-sync
...entries managed automatically...
## docker-hostsfile-sync-end
```

Everything outside these markers is left untouched.

### Hostname Generation Rules

| Source | Example | Generated Hostname | Target IP |
|--------|---------|-------------------|-----------|
| Container name | `webapp` | `webapp.networkname` | Container IP |
| Simple network alias | `frontend` | `frontend.networkname` | Container IP |
| URL-like network alias | `app.example.com` | `app.example.com` | Reverse proxy IP |
| `DOMAIN_NAME` env var | `api.local` | `api.local` | Reverse proxy IP |
| `VIRTUAL_HOST` env var | `www.app.local` | `www.app.local` | Reverse proxy IP |

**URL-like aliases** (containing a `.`) are assumed to be real domain names that should route through your reverse proxy. **Simple aliases** (no `.`) are combined with the network name.

## Running Locally (Development)

```bash
# Install dependencies
composer install

# Run (outputs to ./tmp-hosts for testing)
php bin/docker-api synchronize-hosts -v

# Run with reverse proxy support
php bin/docker-api synchronize-hosts -v --reverse-proxy-host-ip=172.16.238.100

# Run targeting /etc/hosts (requires sudo)
sudo php bin/docker-api synchronize-hosts --hosts_file=/etc/hosts -v

# View logs
docker compose logs -f docker-hostfile-sync
```

## Troubleshooting

### Hosts file not updating

1. Check the container has write access to the hosts file:
   ```bash
   docker exec docker-hostfile-sync ls -la /app/hosts
   ```

2. Check logs for errors:
   ```bash
   docker logs docker-hostfile-sync
   ```

### Container not appearing in hosts file

Containers must be "exposed" (have at least one network with an IP address). Check your container:

```bash
docker inspect <container_name> --format '{{json .NetworkSettings.Networks}}'
```

## License

MIT
