<?php

declare(strict_types=1);

namespace WebProject\DockerApiClient\Dto;

class HostsFileEntryDto
{
    public function __construct(
        public string $id,
        public array $networks,
        public array $ipAddresses,
        public array $aliases,
    ) {
    }
}
