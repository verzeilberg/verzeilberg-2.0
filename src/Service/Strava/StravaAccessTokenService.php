<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Strava\AccessToken;
use App\Repository\Strava\AccessTokenRepository;

class StravaAccessTokenService
{
    private AccessTokenRepository $accessTokenRepository;
    private StravaOAuthService $oauthService;

    public function __construct(
        AccessTokenRepository $accessTokenRepository,
        StravaOAuthService $oauthService
    ) {
        $this->accessTokenRepository = $accessTokenRepository;
        $this->oauthService = $oauthService;
    }

    public function createAccessToken(array $data): AccessToken
    {
        $expiresAt = new \DateTimeImmutable();
        $expiresAt = $expiresAt->setTimestamp($data['expires_at'] ?? time() + 21600);

        $accessToken = new AccessToken();
        $accessToken->setExpiresAt($expiresAt);
        $accessToken->setRefreshToken($data['refresh_token'] ?? '');
        $accessToken->setAccessToken($data['access_token'] ?? '');

        $this->accessTokenRepository->save($accessToken);

        return $accessToken;
    }

    public function getValidAccessToken(): ?AccessToken
    {
        return $this->accessTokenRepository->findValidToken();
    }

    public function getLatestAccessToken(): ?AccessToken
    {
        return $this->accessTokenRepository->findLatest();
    }

    public function isTokenValid(?AccessToken $token): bool
    {
        if (!$token) {
            return false;
        }

        return !$token->isExpired();
    }

    public function refreshAccessToken(): ?AccessToken
    {
        $token = $this->getLatestAccessToken();
        if (!$token) {
            return null;
        }

        try {
            $newTokenData = $this->oauthService->refreshToken($token->getRefreshToken());
            return $this->createAccessToken($newTokenData);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getAccessToken(): ?string
    {
        $token = $this->getValidAccessToken();

        if (!$token) {
            $token = $this->refreshAccessToken();
        }

        return $token?->getAccessToken();
    }
}