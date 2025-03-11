<?php

declare(strict_types=1);

namespace WebProject\DockerApiClient\Util;

use WebProject\DockerApi\Library\Generated\Model\ContainerInspectResponse;
use WebProject\DockerApiClient\Dto\DockerContainerDto;
use function array_map;
use function array_unique;
use function explode;

final readonly class ContainerResponseToContainerDtoUtil
{
    public function __invoke(
        ContainerInspectResponse $containerInspect,
    ): DockerContainerDto {
        $ipAddresses = [];
        $networks    = [];

        foreach ($containerInspect->getNetworkSettings()->getNetworks() as $networkName => $network) {
            if ('' === $network->getIPAddress()) {
                continue;
            }
            $networks[$networkName]['aliases'] = array_unique($network->getAliases() ?? []);
            $networks[$networkName]['ip']      = $network->getIPAddress();
            $ipAddresses[]                     = $network->getIPAddress();
        }

        return new DockerContainerDto(
            id: $containerInspect->getId(),
            name: $containerInspect->getName(),
            image: $containerInspect->getImage(),
            running: $containerInspect->getState()?->getRunning() ?? false,
            envVariables: array_map(static fn (string $envVar) => explode('=', $envVar), $containerInspect->getConfig()->getEnv() ?? []),
            ipAddresses: $ipAddresses,
            networks: $networks,
            ports: (array) ($containerInspect->getNetworkSettings()->getPorts() ?? []),
        );
    }
}
