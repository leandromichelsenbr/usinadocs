<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Authentication;

use RuntimeException;

final class OAuthClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function origin(): string
    {
        return rtrim((string) ($this->config['origin'] ?? ''), '/');
    }

    public function providers(): array
    {
        $options = [];
        foreach (['google' => 'Google', 'microsoft' => 'Microsoft'] as $provider => $label) {
            $settings = $this->config[$provider] ?? [];
            if (preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]+)?$~D', $this->origin())
                && !empty($settings['client_id']) && !empty($settings['client_secret'])) {
                $options[$provider] = $label;
            }
        }

        return $options;
    }

    public function authorizationUrl(string $provider, string $state, string $verifier): string
    {
        [$authorize, , , $scope] = $this->endpoints($provider);

        return $authorize.'?'.http_build_query([
            'client_id' => $this->config[$provider]['client_id'],
            'redirect_uri' => $this->callback($provider),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identity(string $provider, string $code, string $verifier): array
    {
        [, $tokenEndpoint, $profileEndpoint, $scope] = $this->endpoints($provider);
        $token = $this->json($tokenEndpoint, [
            'client_id' => $this->config[$provider]['client_id'],
            'client_secret' => $this->config[$provider]['client_secret'],
            'redirect_uri' => $this->callback($provider),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $verifier,
            'scope' => $scope,
        ]);
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '' || preg_match('/[\r\n]/', $accessToken)) {
            throw new RuntimeException('Provider returned an invalid access token.');
        }

        // As in AdvPL Guia, get the profile directly from the provider over TLS.
        // Only Google's explicit verification can authorize the initial invitation.
        $profile = $this->json($profileEndpoint, null, $accessToken);
        $subject = $profile[$provider === 'google' ? 'sub' : 'id'] ?? null;
        if (!is_string($subject) || $subject === '') {
            throw new RuntimeException('Provider returned no account identifier.');
        }
        $name = $profile[$provider === 'google' ? 'name' : 'displayName'] ?? null;
        $email = $provider === 'google' ? ($profile['email'] ?? null) : ($profile['mail'] ?? $profile['userPrincipalName'] ?? null);

        return [
            'subject' => $subject,
            'name' => is_string($name) && trim($name) !== '' ? trim($name) : $this->providers()[$provider],
            'email' => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'verified' => $provider === 'google' && ($profile['email_verified'] ?? false) === true,
        ];
    }

    private function callback(string $provider): string
    {
        return $this->origin().'/auth/'.$provider.'/callback';
    }

    private function endpoints(string $provider): array
    {
        if (!isset($this->providers()[$provider])) {
            throw new RuntimeException('Unknown or disabled provider.');
        }
        if ($provider === 'google') {
            return [
                'https://accounts.google.com/o/oauth2/v2/auth',
                'https://oauth2.googleapis.com/token',
                'https://openidconnect.googleapis.com/v1/userinfo',
                'openid email profile',
            ];
        }

        $tenant = $this->config['microsoft']['tenant'] ?? 'common';
        if (!is_string($tenant) || !preg_match('/^[a-zA-Z0-9.-]+$/D', $tenant)) {
            throw new RuntimeException('Invalid Microsoft tenant.');
        }
        $base = 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0';

        return [
            $base.'/authorize',
            $base.'/token',
            'https://graph.microsoft.com/v1.0/me?$select=id,displayName,mail,userPrincipalName',
            'openid email profile User.Read',
        ];
    }

    private function json(string $url, ?array $form = null, ?string $accessToken = null): array
    {
        $handle = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($form, '', '&', PHP_QUERY_RFC3986));
        }
        if ($accessToken !== null) {
            $headers[] = 'Authorization: Bearer '.$accessToken;
        }
        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
        ]);
        $body = curl_exec($handle);
        if (!is_string($body) || curl_getinfo($handle, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new RuntimeException('Provider request failed.');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Provider returned invalid JSON.');
        }

        return $decoded;
    }
}
