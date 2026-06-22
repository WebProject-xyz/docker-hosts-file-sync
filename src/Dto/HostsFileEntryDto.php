<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Dto;

use Stringable;

class HostsFileEntryDto implements Stringable
{
    public function __construct(
        public private(set) string $ip,
        /**
         * @var array<string>
         */
        public private(set) array $hostnames,
    ) {
    }

    public function __toString(): string
    {
        return $this->ip . ' ' . implode(' ', $this->hostnames);
    }
}
