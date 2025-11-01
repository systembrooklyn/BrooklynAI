<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacebookAuthService;
use App\Models\FacebookAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FacebookAuthController extends Controller
{
    // Step 1: Redirect to Facebook login
    public function redirectToFacebook()
    {
        $appId = config('services.meta.app_id');
        $redirectUri = config('services.meta.redirect_uri');
        $permissions = implode(',', [
            'pages_show_list',
            'pages_read_engagement',
            'pages_read_user_content',
            'pages_manage_metadata',
            'pages_manage_posts',
            'pages_manage_engagement',
            'read_insights',
            'business_management',
            // 'leads_retrieval',
        ]);

        $loginUrl = "https://www.facebook.com/v24.0/dialog/oauth?" . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => $permissions,
        ]);

        return redirect($loginUrl);
            
    }

    // Step 2: Handle Facebook callback
    public function handleCallback(Request $request)
    {
        $code = $request->query('code');
        if (!$code) {
            return response()->json(['error' => 'Missing code'], 400);
        }

        try {
            $appId = config('services.meta.app_id');
            $appSecret = config('services.meta.app_secret');
            $redirectUri = config('services.meta.redirect_uri');

            // Exchange code for short-lived token
            $tokenResponse = Http::asForm()->post("https://graph.facebook.com/v24.0/oauth/access_token", [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            $tokenData = $tokenResponse->json();
            if (!isset($tokenData['access_token'])) {
                return response()->json(['error' => 'Token exchange failed', 'details' => $tokenData], 400);
            }

            // Exchange for long-lived token
            $longResponse = Http::asForm()->get("https://graph.facebook.com/v24.0/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $tokenData['access_token'],
            ]);

            $longData = $longResponse->json();
            if (!isset($longData['access_token'])) {
                return response()->json(['error' => 'Long-lived token failed', 'details' => $longData], 400);
            }

            $longLivedToken = $longData['access_token'];

            // Get user ID
            $userResponse = Http::get("https://graph.facebook.com/v24.0/me?access_token=" . urlencode($longLivedToken));
            $userData = $userResponse->json();
            if (!isset($userData['id'])) {
                return response()->json(['error' => 'Failed to get user ID', 'details' => $userData], 400);
            }

            $userId = $userData['id'];

            // Save to DB
            FacebookAccount::updateOrCreate(
                ['facebook_user_id' => $userId],
                ['access_token' => $longLivedToken]
            );

            return response()->json([
                'message' => 'Facebook account linked successfully',
                'facebook_user_id' => $userId,
                'next_steps' => 'Use this facebook_user_id in GEtPages API calls'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Helper: get token from DB
    private function getAccessToken(string $userId): string
    {
        $account = FacebookAccount::where('facebook_user_id', $userId)->first();
        if (!$account) {
            throw new \Exception('Facebook account not found. Please log in first.');
        }
        return $account->access_token;
    }

    // Step 3: Get Pages
    public function getPages(Request $request)
    {
        $userId = $request->query('facebook_user_id');
        if (!$userId) {
            return response()->json(['error' => 'Missing facebook_user_id'], 400);
        }

        try {
            $accessToken = $this->getAccessToken($userId);
            $response = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,category,access_token'
            ]);

            $data = $response->json();
            if (!isset($data['data'])) {
                return response()->json(['message' => 'Failed to fetch pages', 'data' => $data], 400);
            }

            return response()->json($data['data']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // Step 4: Get Leads
    public function getLeads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'facebook_user_id' => 'required|string',
            'page_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $accessToken = $this->getAccessToken($request->facebook_user_id);
            $pageId = $request->page_id;

            // Get page access token
            $pagesResponse = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,access_token'
            ]);
            $pages = $pagesResponse->json()['data'] ?? [];

            $pageToken = null;
            foreach ($pages as $page) {
                if ($page['id'] === $pageId) {
                    $pageToken = $page['access_token'];
                    break;
                }
            }

            if (!$pageToken) {
                return response()->json(['error' => 'Page not found or no access'], 403);
            }

            // Get leadgen forms
            $formsResponse = Http::get("https://graph.facebook.com/v24.0/{$pageId}/leadgen_forms", [
                'access_token' => $pageToken,
                'fields' => 'id,name'
            ]);
            $forms = $formsResponse->json()['data'] ?? [];

            $leads = [];
            foreach ($forms as $form) {
                $leadsResponse = Http::get("https://graph.facebook.com/v24.0/{$form['id']}/leads", [
                    'access_token' => $pageToken,
                    'fields' => 'id,created_time,field_data,ad_id,ad_name'
                ]);
                $formLeads = $leadsResponse->json()['data'] ?? [];
                foreach ($formLeads as $lead) {
                    $lead['form_name'] = $form['name'] ?? 'Unknown';
                    $leads[] = $lead;
                }
            }

            return response()->json($leads);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // Step 5: Get Lead Details
    public function getLeadDetails(string $leadId, Request $request)
    {
        $userId = $request->query('facebook_user_id');
        if (!$userId) {
            return response()->json(['error' => 'Missing facebook_user_id'], 400);
        }

        try {
            $accessToken = $this->getAccessToken($userId);

            // Try all pages until lead is found
            $pagesResponse = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'access_token'
            ]);
            $pages = $pagesResponse->json()['data'] ?? [];

            foreach ($pages as $page) {
                try {
                    $leadResponse = Http::get("https://graph.facebook.com/v24.0/{$leadId}", [
                        'access_token' => $page['access_token'],
                        'fields' => 'id,created_time,field_data,ad_id,ad_name,form_id'
                    ]);
                    $lead = $leadResponse->json();
                    if (isset($lead['id'])) {
                        return response()->json($lead);
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return response()->json(['error' => 'Lead not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // Step 6: Get Page Insights


    public function getPageInsights(Request $request)
    {
        $request->validate([
            'page_access_token' => 'required|string',
            'page_id' => 'required|string',
        ]);

        try {
            // no page_engaged_user
            $metrics = 'page_impressions,page_fan_adds,page_impressions_unique';

            //  FIXED: No space after /v24.0/
            $response = Http::get("https://graph.facebook.com/v24.0/{$request->page_id}/insights", [
                'access_token' => $request->page_access_token,
                'metric' => $metrics,
                'period' => 'day',
                'since' => now()->subDays(30)->timestamp,
                'until' => now()->timestamp,
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                return response()->json([
                    'error' => 'Meta API error',
                    'message' => $data['error']['message'] ?? 'Unknown error'
                ], 400);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch insights',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

    // public function getPageInsights(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'facebook_user_id' => 'required|string',
    //         'page_id' => 'required|string',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 400);
    //     }

    //     try {
    //         $accessToken = $this->getAccessToken($request->facebook_user_id);
    //         $pageId = $request->page_id;

    //         // Get page access token
    //         $pagesResponse = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
    //             'access_token' => $accessToken,
    //             'fields' => 'id,access_token'
    //         ]);
    //         $pages = $pagesResponse->json()['data'] ?? [];

    //         $pageToken = null;
    //         foreach ($pages as $page) {
    //             if ($page['id'] === $pageId) {
    //                 $pageToken = $page['access_token'];
    //                 break;
    //             }
    //         }

    //         if (!$pageToken) {
    //             return response()->json(['error' => 'Page not found'], 403);
    //         }

    //         $metrics = 'page_fan_adds,page_views_total,page_engaged_users';
    //         $insightsResponse = Http::get("https://graph.facebook.com/v24.0/{$pageId}/insights", [
    //             'access_token' => $pageToken,
    //             'metric' => $metrics,
    //             'period' => 'day',
    //             'since' => now()->subDays(7)->timestamp,
    //             'until' => now()->timestamp,
    //         ]);

    //         $insights = $insightsResponse->json();
    //         return response()->json($insights['data'] ?? []);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 400);
    //     }
    // }
