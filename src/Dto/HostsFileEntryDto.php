<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Dto;

use Stringable;

class HostsFileEntryDto implements Stringable
{
    public function __construct(
        public string $ip,
        /**
         * @var array<string>
         */
        public array $hostnames,
    ) {
    }

    public function __toString(): string
    {
        return $this->ip . ' ' . implode(' ', $this->hostnames);
    }
}
