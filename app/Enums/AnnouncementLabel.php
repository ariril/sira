<?php

namespace App\Enums;

enum AnnouncementLabel: string
{
    case PENTING = 'penting';
    case INFO    = 'info';
    case UPDATE  = 'update';
}
