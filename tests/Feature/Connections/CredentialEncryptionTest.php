<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_and_refresh_tokens_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();

        $model = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-x',
            'access_token' => 'PLAINTEXT-ACCESS',
            'refresh_token' => 'PLAINTEXT-REFRESH',
            'scopes' => ['openid', 'email', 'profile'],
            'status' => 'active',
        ]);

        $raw = DB::table('connections')->where('id', $model->id)->first();
        $this->assertNotNull($raw);
        $this->assertNotSame('PLAINTEXT-ACCESS', $raw->access_token);
        $this->assertNotSame('PLAINTEXT-REFRESH', $raw->refresh_token);

        $fresh = ConnectionModel::query()->findOrFail($model->id);
        $this->assertSame('PLAINTEXT-ACCESS', $fresh->access_token);
        $this->assertSame('PLAINTEXT-REFRESH', $fresh->refresh_token);
    }

    public function test_connection_model_serialization_hides_credentials(): void
    {
        $user = User::factory()->create();

        $model = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-y',
            'access_token' => 'SECRET-A',
            'refresh_token' => 'SECRET-R',
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $array = $model->toArray();
        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);
        $this->assertArrayNotHasKey('last_error_message', $array);
    }
}
