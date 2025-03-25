<?php

declare(strict_types=1);

namespace WebProject\DockerApiClient\Util;

use WebProject\DockerApiClient\Dto\DockerContainerDto;
use function array_merge;
use function array_unique;
use function array_walk;
use function current;
use function implode;
use function sprintf;
use function str_contains;
use function substr;

final readonly class ContainerToHostsFileLinesUtil
{
    /**
     * @param array<string> $extractFromEnvVars
     *
     * @return array<string>
     */
    public function __invoke(DockerContainerDto $container, string $tld, array $extractFromEnvVars, ?string $reverseProxyIp = null): array
    {
        $lines                 = [];
        $reverseProxyHostnames = [];
        // Global
        if (!empty($container->ipAddresses)) {
            $ip = current($container->ipAddresses);

            $lines[$ip] = implode(
                ' ',
                $container->getHostnames($tld)
            );
        }

        // Networks
        foreach ($container->networks as $networkName => $conf) {
            $ip = $conf['ip'];

            $aliases       = $conf['aliases'] ?? [];
            $containerName = $container->getName();
            if (str_starts_with($containerName, '/')) {
                $aliases[] = substr($containerName, 1);
            } else {
                $aliases[] = $containerName;
            }

            $hosts = [];
            foreach (array_unique($aliases) as $alias) {
                if (str_contains($alias, '.')) {
                    // alias looks like a url - should be added to proxy target
                    $reverseProxyHostnames[] = $alias;
                    continue;
                }

                $hosts[] = $alias . '.' . $networkName;
            }

            $lines[$ip] = sprintf('%s%s', isset($lines[$ip]) ? $lines[$ip] . ' ' : '', implode(' ', $hosts));
        }

        if ($reverseProxyIp) {
            $lines = $this->handleReverseProxyLines($reverseProxyHostnames, $container, $extractFromEnvVars, $lines, $reverseProxyIp);
        }

        array_walk($lines, static function (&$host, $ip) {
            $host = $ip . ' ' . $host;
        });

        return $lines;
    }

    /**
     * @param array<string> $extractFromEnvVars
     * @param array<string> $lines
     * @param array<string> $reverseProxyHostnames
     *
     * @return array<string>
     */
    private function handleReverseProxyLines(array $reverseProxyHostnames, DockerContainerDto $container, array $extractFromEnvVars, array $lines, string $reverseProxyIp): array
    {
        $reverseProxyTargets = array_unique(array_merge($reverseProxyHostnames, $container->extractUrlsFromEnvVars($extractFromEnvVars)));
        if ([] !== $reverseProxyTargets) {
            $lines[$reverseProxyIp] = implode(
                ' ',
                $reverseProxyTargets
            );
        }

        return $lines;
    }
}
