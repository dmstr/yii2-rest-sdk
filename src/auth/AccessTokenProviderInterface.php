<?php

namespace dmstr\rest\sdk\auth;

interface AccessTokenProviderInterface
{
    /**
     * Returns the full Authorization header value (e.g. "Bearer xxx", "Basic xxx"),
     * or null when no auth is available (skips Authorization header).
     */
    public function getAuthorizationHeader(): ?string;
}
