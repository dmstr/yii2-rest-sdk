<?php

namespace dmstr\rest\sdk\auth;

interface AccessTokenProviderInterface
{
    /**
     * Returns the current access token, or null when no token is available (skips auth header).
     */
    public function getAccessToken(): ?string;
}
