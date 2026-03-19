<?php

namespace dmstr\rest\sdk\auth;

use yii\base\BaseObject;

/**
 * Configurable token provider for static API keys, service tokens, or no auth.
 *
 * - Set $token for a static token
 * - Set neither for unauthenticated requests (null token → no Authorization header)
 */
class TokenProvider extends BaseObject implements AccessTokenProviderInterface
{
    public ?string $token = null;

    public function getAccessToken(): ?string
    {
        return $this->token ?: null;
    }
}
