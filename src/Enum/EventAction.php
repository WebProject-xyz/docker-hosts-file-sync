<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Enum;

enum EventAction: string
{
    case START   = 'start';
    case RESTART = 'restart';
    case STOP    = 'stop';
    case DIE     = 'die';
}
