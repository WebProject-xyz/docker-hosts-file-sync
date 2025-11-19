<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Util;

use WebProject\DockerApiClient\Dto\DockerContainerDto;
use WebProject\DockerHostsFileSync\Dto\HostsFileEntryDto;

use function is_string;

final readonly class ContainerToHostsFileLinesUtil
{
    /**
     * @param array<string> $extractFromEnvVars
     *
     * @return iterable<string, HostsFileEntryDto>
     */
    public function __invoke(
        DockerContainerDto $container,
        string $tld,
        array $extractFromEnvVars,
        ?string $reverseProxyIp = null,
    ): array {
        $ips = [];

        // Global
        if (!empty($container->ipAddresses)) {
            $ip = current($container->ipAddresses);
            if (is_string($ip) && $ip) {
                $ips[$ip] = $container->getHostnames($tld);
            }
        }

        // Networks
        foreach ($container->networks as $networkName => $conf) {
            $ip = $conf['ip'] ?? null;
            if (!is_string($ip) || '' === $ip) {
                continue;
            }

            $aliases       = $conf['aliases'] ?? [];
            $containerName = $container->getName();
            if (str_starts_with($containerName, '/')) {
                $aliases[] = substr($containerName, 1);
            } else {
                $aliases[] = $containerName;
            }
            // alias to hostname
            foreach (array_unique($aliases) as $alias) {
                if (str_contains($alias, '.')) {
                    // alias looks like a url - should be added to proxy target
                    if ($reverseProxyIp) {
                        $ips[$reverseProxyIp][] = $alias;
                    }
                    continue;
                }

                $ips[$ip][] = $alias . '.' . $networkName;
            }
        }

        if ($reverseProxyIp) {
            $ips[$reverseProxyIp] = array_merge(
                $ips[$reverseProxyIp] ?? [],
                $container->extractUrlsFromEnvVars($extractFromEnvVars)
            );
        }

        $lines = [];
        foreach ($ips as $ip => $hostnames) {
            if ([] === $hostnames) {
                continue;
            }
            $lines[$ip] = new HostsFileEntryDto($ip, $hostnames);
        }

        return $lines;
    }
}
