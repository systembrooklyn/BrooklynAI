<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            if ($request->has('hub_verify_token')) {
                $token = config('services.meta.verify_token');
                $challenge = $request->input('hub_challenge');
                if ($request->input('hub_verify_token') === $token) {
                    return response($challenge, 200)->header('Content-Type', 'text/plain');
                }
                return response('Invalid token', 403);
            }

            // Handle real leads
            Log::info('🔥 Facebook Lead Received', $request->all());
            $this->processLead($request->input('entry'));
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Webhook Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response('Server error', 500);
        }
    }

    protected function processLead(array $entries)
    {
        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) === 'leadgen') {
                    $leadId = $change['value']['leadgen_id'] ?? null;
                    $formId = $change['value']['form_id'] ?? null;

                    if ($leadId && $formId) {
                        Log::info("✅ New Lead: {$leadId} from Form: {$formId}");
                        // Optional: Fetch full lead data here
                    }
                }
            }
        }
    }
}
