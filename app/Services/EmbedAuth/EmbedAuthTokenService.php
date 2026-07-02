<?php

declare(strict_types=1);

namespace App\Services\EmbedAuth;

use App\Exceptions\EmbedAuth\InvalidTokenException;
use App\Models\LaunchToken;
use App\Models\PersonalRefreshToken;
use Module\Users\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Module\Projects\Models\Project;

final class EmbedAuthTokenService
{
    private const int LAUNCH_TOKEN_TTL_SECONDS = 300;

    private const int ACCESS_TOKEN_TTL_MINUTES = 30;

    private const int REFRESH_TOKEN_TTL_DAYS = 7;

    /**
     * @return array{launch_token: string, expires_in: int}
     */
    public function createLaunchToken(User $user, Project $project, ?string $allowedOrigin = null): array
    {
        $plainToken = bin2hex(random_bytes(40));

        LaunchToken::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'token_hash' => hash('sha256', $plainToken),
            'allowed_origin' => $allowedOrigin ?? (string)$project->host,
            'expires_at' => now()->addSeconds(self::LAUNCH_TOKEN_TTL_SECONDS),
        ]);

        return [
            'launch_token' => $plainToken,
            'expires_in' => self::LAUNCH_TOKEN_TTL_SECONDS,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws InvalidTokenException
     */
    public function exchange(string $plainToken, ?string $requestOrigin): array
    {
        $launchToken = LaunchToken::query()
            ->with(['user', 'project'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($launchToken === null) {
            throw new InvalidTokenException('Token not found.', 401);
        }

        if ($launchToken->used_at !== null) {
            throw new InvalidTokenException('Token already used.', 401);
        }

        if ($launchToken->expires_at->isPast()) {
            throw new InvalidTokenException('Token expired.', 401);
        }

        $project = $launchToken->project;

        if (!$project->is_active) {
            throw new InvalidTokenException('Project is not active.', 422);
        }

        if ($launchToken->allowed_origin !== '') {
            if ($requestOrigin === null) {
                throw new InvalidTokenException('Origin header is required.', 403);
            }
            if (!$this->originsMatch($requestOrigin, $launchToken->allowed_origin)) {
                throw new InvalidTokenException('Origin not allowed.', 403);
            }
        }

        $user = $launchToken->user;

        if (!$this->userInProject($user, $project)) {
            throw new InvalidTokenException('User is not in project.', 403);
        }

        $launchToken->update(['used_at' => now()]);

        return $this->issueTokenPair($user, $project);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws InvalidTokenException
     */
    public function refresh(string $plainRefreshToken): array
    {
        $refreshToken = PersonalRefreshToken::query()
            ->with(['user', 'project'])
            ->where('token_hash', hash('sha256', $plainRefreshToken))
            ->whereNull('revoked_at')
            ->first();

        if ($refreshToken === null) {
            throw new InvalidTokenException('Refresh token not found.', 401);
        }

        if ($refreshToken->expires_at->isPast()) {
            throw new InvalidTokenException('Refresh token expired.', 401);
        }

        $project = $refreshToken->project;
        $user = $refreshToken->user;

        if (!$project->is_active) {
            throw new InvalidTokenException('Project is not active.', 422);
        }

        if (!$this->userInProject($user, $project)) {
            throw new InvalidTokenException('User is not in project.', 403);
        }

        // Revoke old access token
        if ($refreshToken->personal_access_token_id !== null) {
            PersonalAccessToken::query()
                ->where('id', $refreshToken->personal_access_token_id)
                ->delete();
        }

        $tokenPair = $this->issueTokenPair($user, $project);

        // Revoke old refresh token after issuing new one (rotation)
        $refreshToken->update([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ]);

        return $tokenPair;
    }

    public function logout(string $plainAccessToken): void
    {
        $sanctumToken = PersonalAccessToken::findToken($plainAccessToken);

        if ($sanctumToken === null) {
            return;
        }

        PersonalRefreshToken::query()
            ->where('personal_access_token_id', $sanctumToken->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $sanctumToken->delete();
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function issueTokenPair(User $user, Project $project): array
    {
        $newAccessToken = $user->createToken(
            'embed-access',
            ['embed:access'],
            now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES),
        );

        $plainRefreshToken = bin2hex(random_bytes(40));

        $accessTokenKey = $newAccessToken->accessToken->getKey();
        if (!is_int($accessTokenKey)) {
            throw new \UnexpectedValueException('Personal access token key must be integer.');
        }

        PersonalRefreshToken::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'personal_access_token_id' => $accessTokenKey,
            'token_hash' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS),
        ]);

        return [
            'access_token' => $newAccessToken->plainTextToken,
            'refresh_token' => $plainRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_MINUTES * 60,
        ];
    }

    private function userInProject(User $user, Project $project): bool
    {
        return $user->project_id === $project->id;
    }

    private function originsMatch(string $a, string $b): bool
    {
        return $this->normalizeOrigin($a) === $this->normalizeOrigin($b);
    }

    private function normalizeOrigin(string $origin): string
    {
        $origin = rtrim($origin, '/');

        if (!str_contains($origin, '://')) {
            return 'https://'.$origin;
        }

        return $origin;
    }
}
