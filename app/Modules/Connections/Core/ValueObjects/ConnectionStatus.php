<?php

namespace App\Modules\Connections\Core\ValueObjects;

enum ConnectionStatus: string
{
    case Active = 'active';
    case Error = 'error';
    case NeedsReauth = 'needs_reauth';
    case Revoked = 'revoked';
}
