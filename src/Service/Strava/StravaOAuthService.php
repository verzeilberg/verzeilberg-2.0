<?php

declare(strict_types=1);

namespace App\Service\Strava;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class StravaOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $approvalPrompt;
    private array $scopes;
    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $approvalPrompt = 'force',
        array $scopes = ['read', 'activity:read']
    ) {
        $this->httpClient = $httpClient;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->approvalPrompt = $approvalPrompt;
        $this->scopes = $scopes;
    }

    public function getAuthorizationUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'approval_prompt' => $this->approvalPrompt,
            'scope' => implode(',', $this->scopes),
        ];

        return 'https://www.strava.com/oauth/authorize?' . http_build_query($params);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function exchangeToken(string $code): array
    {
        $response = $this->httpClient->request('POST', 'https://www.strava.com/api/v3/oauth/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ],
        ]);

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->httpClient->request('POST', 'https://www.strava.com/api/v3/oauth/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
        ]);

        return $response->toArray();
    }
}