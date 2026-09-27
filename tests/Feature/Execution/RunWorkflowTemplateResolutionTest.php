<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowTemplateResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function activeWorkflow(User $user, array $steps): int
    {
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        foreach ($steps as $step) {
            $this->postJson('/api/workflows/'.$id.'/steps', $step);
        }

        $this->postJson('/api/workflows/'.$id.'/activate');

        return $id;
    }

    public function test_step_config_resolves_against_trigger_payload(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => [
                    'to' => '{{ trigger.sender }}',
                    'subject' => 'Hello {{ trigger.name }}',
                    'body' => '{{ trigger.body }}',
                ]],
        ]);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => ['sent' => true, 'message_id' => 'm-1'];
        $this->app->instance(ActionInvoker::class, $fake);

        $this->postJson('/api/workflows/'.$id.'/execute', [
            'trigger_payload' => [
                'sender' => 'sender@example.com',
                'name' => 'Alice',
                'body' => 'Hello!',
            ],
        ]);

        $this->assertCount(1, $fake->invocations);
        $this->assertSame('sender@example.com', $fake->invocations[0]['config']['to']);
        $this->assertSame('Hello Alice', $fake->invocations[0]['config']['subject']);
        $this->assertSame('Hello!', $fake->invocations[0]['config']['body']);
    }

    public function test_missing_template_path_fails_execution(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => [
                    'to' => '{{ trigger.missing_key }}',
                    'subject' => 'S',
                    'body' => 'B',
                ]],
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'trigger_payload' => [],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.steps.0.status', 'failed');
    }
}
