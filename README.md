# php-docker-api-client
> A PHP docker API client with a cli tool to sync your hosts file with running docker containers to add hostnames for every network 
> (inspired by https://github.com/iamluc/docker-hostmanager)

## todo:
- [ ] add example usage to readme
- [ ] explain it

## hosts file (/etc/hosts) sync
```shell
docker run -d --name docker-hostfile-sync --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v /etc/hosts:/app/hosts ghcr.io/webproject-xyz/php-docker-api-client:latest 
```

## Example of hosts file
This is based on 2 containers 
* container 1 (jwilder-proxy) has multiple aliases
* container 2 (my-dev-server) has multiple aliases, an alias with a "normal" url (actual.realFancyUrl.tld set on the proxyNet network)


```text
[...your stuff]

## docker-hostsfile-sync
172.16.238.100 jwilder-proxy.docker jwilder-proxy.proxyNet jwilder.proxyNet proxy.local

172.19.0.2 my-dev-server.docker my-dev-server.my-dev-server_default
172.16.238.2 my-dev-server.proxyNet actual.realFancyUrl.tld
## docker-hostsfile-sync-end
```
