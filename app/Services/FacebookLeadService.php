<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookLeadService
{
    protected string $accessToken;
    protected string $apiVersion = 'v21.0';

    public function __construct()
    {
        $this->accessToken = config('services.meta.access_token');

        if (empty($this->accessToken)) {
            throw new \Exception('META_ACCESS_TOKEN is not set in .env');
        }
    }

    /**
     * Fetch leads from your Lead Form ID
     */
    public function fetchLeads(): array
    {
        $formId = config('services.meta.lead_form_id');

        if (empty($formId)) {
            throw new \Exception('META_LEAD_FORM_ID is not set in .env');
        }

        $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->apiVersion}/{$formId}/leads", [
            'access_token' => $this->accessToken,
            'fields' => 'id,created_time,field_data'
        ]);

        if ($response->failed()) {
            Log::error('Facebook API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return $response->json('data', []);
    }
}