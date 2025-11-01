<?php

namespace App\Services;

use Facebook\Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;

class FacebookAuthService
{
    protected Facebook $fb;

public function getUserIdFromToken(string $userAccessToken): string
{
    try {
        $response = $this->fb->get('/me?fields=id', $userAccessToken);
        $user = $response->getGraphNode();
        return $user['id'];
    } catch (\Exception $e) {
        throw new \Exception('Failed to fetch user ID: ' . $e->getMessage());
    }
}


    public function __construct()
    {
        $this->fb = new Facebook([
            'app_id' => config('services.meta.app_id'),
            'app_secret' => config('services.meta.app_secret'),
            'default_graph_version' => 'v24.0',
        ]);
    }

    public function getLoginUrl(string $redirectUri): string
    {
        $helper = $this->fb->getRedirectLoginHelper();
        // Note: leads_retrieval is REQUIRED for leads — add it even if not in your list
        $permissions = [
            'pages_show_list',
            'pages_read_engagement',
            'pages_read_user_content',
            'pages_manage_metadata',
            'pages_manage_posts',
            'pages_manage_engagement',
            'read_insights',
            'business_management',
            // 'leads_retrieval', //  REQUIRED for /leads — must be granted to your account
        ];
        return $helper->getLoginUrl($redirectUri, $permissions);
    }

    public function getAccessTokenFromCallback(string $redirectUri)
    {
        $helper = $this->fb->getRedirectLoginHelper();
        try {
            return $helper->getAccessToken($redirectUri);
        } catch (FacebookResponseException | FacebookSDKException $e) {
            throw new \Exception('Facebook auth error: ' . $e->getMessage());
        }
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): string
    {
        try {
            $oAuth2Client = $this->fb->getOAuth2Client();
            $longLivedToken = $oAuth2Client->getLongLivedAccessToken($shortLivedToken);
            return $longLivedToken->getValue();
        } catch (\Exception $e) {
            throw new \Exception('Token exchange failed: ' . $e->getMessage());
        }
    }

    public function getUserPages(string $userAccessToken): array
    {
        try {
            $response = $this->fb->get('/me/accounts?fields=id,name,category,access_token', $userAccessToken);
            return $response->getGraphEdge()->asArray();
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch pages: ' . $e->getMessage());
        }
    }

    //  Requires 'leads_retrieval' permission
    public function getPageLeads(string $pageId, string $pageAccessToken): array
    {
        try {
            $formsResponse = $this->fb->get("/{$pageId}/leadgen_forms?fields=id,name", $pageAccessToken);
            $forms = $formsResponse->getGraphEdge()->asArray();

            $leads = [];
            foreach ($forms as $form) {
                $leadsResponse = $this->fb->get("/{$form['id']}/leads?fields=id,created_time,field_data,ad_id,ad_name", $pageAccessToken);
                $formLeads = $leadsResponse->getGraphEdge()->asArray();
                foreach ($formLeads as $lead) {
                    $lead['form_name'] = $form['name'] ?? 'Unknown Form';
                    $leads[] = $lead;
                }
            }
            return $leads;
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch leads (requires leads_retrieval permission): ' . $e->getMessage());
        }
    }

    public function getLeadDetails(string $leadId, string $accessToken): array
    {
        try {
            $response = $this->fb->get("/{$leadId}?fields=id,created_time,field_data,ad_id,ad_name,form_id", $accessToken);
            return $response->getGraphNode()->asArray();
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch lead details: ' . $e->getMessage());
        }
    }

    public function getPageInsights(string $pageId, string $pageAccessToken): array
    {
        try {
            $metrics = 'page_fan_adds,page_views_total,page_engaged_users,page_impressions';
            $since = now()->subDays(7)->timestamp;
            $until = now()->timestamp;

            $response = $this->fb->get("/{$pageId}/insights?metric={$metrics}&since={$since}&until={$until}", $pageAccessToken);
            return $response->getGraphEdge()->asArray();
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch insights: ' . $e->getMessage());
        }
    }
}