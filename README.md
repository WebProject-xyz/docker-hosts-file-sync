# docker-hosts-file-sync

> A PHP docker API client with a cli tool to sync your hosts file with running docker containers to add hostnames for every network 
> (inspired by https://github.com/iamluc/docker-hostmanager)

## Example
```shell
bin/docker-api --help

# 
## add --hosts_file=/etc/hosts and run with sudo to sync file OR see result in ./tmp-hosts if not
#

# use php 8.3 with symfony cli
symfony php bin/docker-api synchronize-hosts -v --reverse-proxy-host-ip=172.16.238.100
# on php 8.3+
php bin/docker-api synchronize-hosts -v --reverse-proxy-host-ip=172.16.238.100
```

## hosts file (/etc/hosts) sync
```shell
docker pull ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
docker run -d --name docker-hostfile-sync --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v /etc/hosts:/app/hosts ghcr.io/webproject-xyz/docker-hosts-file-sync:latest
# or with reverse-proxy-ip e.g. "172.16.238.100"
docker run -d --name docker-hostfile-sync --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v /etc/hosts:/app/hosts ghcr.io/webproject-xyz/docker-hosts-file-sync:latest --reverse-proxy-host-ip=172.16.238.100
```

## Example of hosts file
This is based on 2 containers 
* container 1 (jwilder-proxy) has multiple aliases
* container 2 (actual_server) has multiple aliases, an alias with a "normal" url (actual.realFancyUrl.tld set on the proxyNet network)


```text
[...your stuff]

## docker-hostsfile-sync
# container - reverse proxy
172.16.238.100 proxy.local

# container - default network
172.19.0.2 actual_server.docker actual_server.actual-server_default

# container - proxyNet network (reverse proxy net)
172.16.238.2 actual_server.proxyNet

# reverse proxy entry from container env var: DOMAIN_NAME (multiple possible with)
# and reverse proxy entry from container network alias (with dot like "actual.my-reverse-proxy-url.xyz")
172.16.238.100 actual.my-reverse-proxy-url.xyz dev-actual.my-reverse-proxy-url.xyz
## docker-hostsfile-sync-end
```
