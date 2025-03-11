<?php

declare(strict_types=1);

namespace WebProject\DockerApiClient\Factory;

use Symfony\Component\Console\Style\SymfonyStyle;
use WebProject\DockerApiClient\Service\DockerService;
use WebProject\DockerApiClient\Service\SynchronizeHostsFileService;

class SynchronizeHostsFileServiceFactory
{
    private function __construct()
    {
    }

    public static function create(
        string $hostsFile,
        string $tld,
        ?SymfonyStyle $io = null,
    ): SynchronizeHostsFileService {
        return new SynchronizeHostsFileService(
            dockerService: new DockerService(
                dockerApiClient: ClientFactory::create(),
            ),
            hostsFile: $hostsFile,
            tld: $tld,
            consoleOutput: $io,
        );
    }
}
