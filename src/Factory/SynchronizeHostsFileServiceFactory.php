<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Factory;

use Symfony\Component\Console\Style\SymfonyStyle;
use WebProject\DockerApiClient\Factory\ClientFactory;
use WebProject\DockerApiClient\Service\DockerService;
use WebProject\DockerHostsFileSync\Service\SynchronizeHostsFileService;

class SynchronizeHostsFileServiceFactory
{
    private function __construct()
    {
    }

    public static function create(
        string $hostsFile,
        string $tld,
        ?string $reverseProxyIp,
        ?SymfonyStyle $io = null,
    ): SynchronizeHostsFileService {
        return new SynchronizeHostsFileService(
            dockerService: new DockerService(
                dockerApiClient: ClientFactory::create(),
            ),
            hostsFile: $hostsFile,
            tld: $tld,
            reverseProxyIp: $reverseProxyIp,
            consoleOutput: $io,
        );
    }
}
