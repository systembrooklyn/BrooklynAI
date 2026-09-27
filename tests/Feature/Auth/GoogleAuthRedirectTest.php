<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class GoogleAuthRedirectTest extends TestCase
{
    public function test_google_redirect_returns_302_to_google_oauth(): void
    {
        $response = $this->get('/api/auth/google/redirect');

        $response->assertStatus(302);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('accounts.google.com', $location);
    }

    public function test_redirect_google_returns_302_with_expected_oauth_parameters(): void
    {
        $response = $this->get('/api/auth/google/redirect-google');

        $response->assertStatus(302);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('accounts.google.com', $location);

        $query = parse_url($location, PHP_URL_QUERY);
        parse_str((string) $query, $params);

        $this->assertSame('offline', $params['access_type'] ?? null);
        $this->assertSame('consent', $params['prompt'] ?? null);
        $this->assertStringContainsString('gmail.send', $params['scope'] ?? '');
        $this->assertStringContainsString('calendar.events', $params['scope'] ?? '');
        $this->assertStringContainsString('spreadsheets', $params['scope'] ?? '');
        $this->assertStringContainsString('analytics.readonly', $params['scope'] ?? '');
    }
}
