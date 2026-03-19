<?php

namespace dmstr\rest\sdk\auth;

use yii\base\BaseObject;

/**
 * Configurable token provider for static API keys, service tokens, or no auth.
 *
 * - Set $token and optionally $scheme to control the Authorization header
 * - Omit $token for unauthenticated requests (null → no Authorization header)
 */
class TokenProvider extends BaseObject implements AccessTokenProviderInterface
{
    public ?string $token = null;

    public string $scheme = 'Bearer';

    public function getAuthorizationHeader(): ?string
    {
        if (empty($this->token)) {
            return null;
        }

        return $this->scheme . ' ' . $this->token;
    }
}
