<?php

namespace App\Modules\Integrations\Core\ValueObjects;

enum TriggerStrategy: string
{
    case Poll = 'poll';
    case Webhook = 'webhook';
    case ProviderPush = 'provider_push';
    case Schedule = 'schedule';
}
