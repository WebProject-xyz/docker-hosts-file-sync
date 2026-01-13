# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A PHP CLI tool that synchronizes your system's hosts file with running Docker containers. It listens for Docker API events (container start/stop/restart/die) and automatically updates the hosts file with container IP addresses and hostnames.

## Build and Development Commands

```bash
# Install dependencies
composer install

# Run the CLI tool locally (outputs to ./tmp-hosts by default)
php bin/docker-api synchronize-hosts -v

# Run with reverse proxy IP support
php bin/docker-api synchronize-hosts -v --reverse-proxy-host-ip=172.16.238.100

# Run targeting actual hosts file (requires sudo)
sudo php bin/docker-api synchronize-hosts --hosts_file=/etc/hosts

# Run tests
vendor/bin/codecept run Unit

# Run a single test file
vendor/bin/codecept run Unit tests/Unit/Util/ContainerToHostsFileLineUtilTest.php

# Run a specific test method
vendor/bin/codecept run Unit tests/Unit/Util/ContainerToHostsFileLineUtilTest.php:testGetExpectedHostFileWithIp

# Code style fix
vendor/bin/php-cs-fixer fix

# Code style check (dry-run)
vendor/bin/php-cs-fixer fix --dry-run
```

## Architecture

The codebase follows a simple layered architecture:

### Entry Point
- `bin/docker-api` - Symfony Console application with `synchronize-hosts` as the default command

### Core Components

**Command Layer** (`src/Command/`)
- `SynchronizeHostsCommand` - CLI command that accepts options for hosts file path, TLD suffix, and reverse proxy IP

**Service Layer** (`src/Service/`)
- `SynchronizeHostsFileService` - Main service that:
  - Initializes by scanning all running containers via Docker API
  - Listens for Docker events (start/restart/stop/die) in an event loop
  - Regenerates the hosts file section between `## docker-hostsfile-sync` markers
  - Tracks active containers in memory and updates hosts file on each event

**Utility Layer** (`src/Util/`)
- `ContainerToHostsFileLinesUtil` - Converts a `DockerContainerDto` into hosts file entries by:
  - Extracting IP addresses from container networks
  - Building hostnames from container name, network names, and aliases
  - Handling reverse proxy entries for URL-like aliases (containing dots)
  - Reading hostnames from `DOMAIN_NAME` and `VIRTUAL_HOST` environment variables

**DTO Layer** (`src/Dto/`)
- `HostsFileEntryDto` - Simple value object representing a hosts file line (IP + hostnames)

**Factory Layer** (`src/Factory/`)
- `SynchronizeHostsFileServiceFactory` - Creates the service with Docker API client from `webproject-xyz/docker-api-client`

### External Dependency
The project relies on `webproject-xyz/docker-api-client` for:
- `DockerService` - High-level Docker API operations
- `DockerContainerDto` - Container data representation
- `ContainerEvent` - Docker event stream data

## Testing

Tests use Codeception with the Unit suite. Test files follow the pattern `tests/Unit/**/*Test.php`.

## Code Style

Uses PHP-CS-Fixer with:
- PSR-12 + Symfony rules
- Strict types required
- PHP 8.3+ migration rules
- Aligned binary operators