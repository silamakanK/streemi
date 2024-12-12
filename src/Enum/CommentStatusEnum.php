<?php

declare(strict_types=1);

namespace App\Enum;

enum CommentStatusEnum: string
{
    case VALID = 'valid';
    case WAITING = 'waiting';
    case REJECTED = 'rejected';
}