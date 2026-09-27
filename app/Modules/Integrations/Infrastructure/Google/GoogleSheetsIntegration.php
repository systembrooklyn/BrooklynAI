<?php

namespace App\Modules\Integrations\Infrastructure\Google;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;

final class GoogleSheetsIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        $spreadsheetsReadonly = ScopeIdentifier::fromString('https://www.googleapis.com/auth/spreadsheets.readonly');
        $spreadsheets = ScopeIdentifier::fromString('https://www.googleapis.com/auth/spreadsheets');
        $driveReadonly = ScopeIdentifier::fromString('https://www.googleapis.com/auth/drive.readonly');

        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.sheets'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Google Sheets',
            description: 'Read, write, and manage spreadsheet data through Google Sheets.',
            category: 'spreadsheets',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            actions: [
                new ActionDefinition(
                    key: 'list_spreadsheets',
                    label: 'List Spreadsheets',
                    description: 'List spreadsheets accessible to the connected account.',
                    requiredScopes: [$driveReadonly, $spreadsheetsReadonly],
                ),
                new ActionDefinition(
                    key: 'get_spreadsheet',
                    label: 'Get Spreadsheet',
                    description: 'Retrieve a spreadsheet and its sheets/tabs.',
                    requiredScopes: [$spreadsheetsReadonly],
                ),
                new ActionDefinition(
                    key: 'add_sheet',
                    label: 'Add Sheet',
                    description: 'Add a new sheet/tab to a spreadsheet.',
                    requiredScopes: [$spreadsheets],
                ),
                new ActionDefinition(
                    key: 'delete_sheet',
                    label: 'Delete Sheet',
                    description: 'Delete a sheet/tab from a spreadsheet.',
                    requiredScopes: [$spreadsheets],
                ),
                new ActionDefinition(
                    key: 'get_data',
                    label: 'Get Data',
                    description: 'Read values from a range in a spreadsheet.',
                    requiredScopes: [$spreadsheetsReadonly],
                ),
                new ActionDefinition(
                    key: 'update_data',
                    label: 'Update Data',
                    description: 'Update values in a range in a spreadsheet.',
                    requiredScopes: [$spreadsheets],
                ),
                new ActionDefinition(
                    key: 'append_data',
                    label: 'Append Data',
                    description: 'Append values to a range in a spreadsheet.',
                    requiredScopes: [$spreadsheets],
                ),
                new ActionDefinition(
                    key: 'clear_data',
                    label: 'Clear Data',
                    description: 'Clear values in a range in a spreadsheet.',
                    requiredScopes: [$spreadsheets],
                ),
                new ActionDefinition(
                    key: 'append_row_by_headers',
                    label: 'Append Row By Headers',
                    description: 'Append a new row mapped by header names.',
                    requiredScopes: [$spreadsheets],
                ),
            ],
            triggers: [],
        );
    }
}
