<?php

namespace dmstr\rest\sdk\tests\auth;

use dmstr\rest\sdk\auth\AccessTokenProviderInterface;
use dmstr\rest\sdk\auth\TokenProvider;
use PHPUnit\Framework\TestCase;

class TokenProviderTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $provider = new TokenProvider();
        $this->assertInstanceOf(AccessTokenProviderInterface::class, $provider);
    }

    public function testReturnsBearerHeaderByDefault(): void
    {
        $provider = new TokenProvider(['token' => 'my-api-key']);
        $this->assertSame('Bearer my-api-key', $provider->getAuthorizationHeader());
    }

    public function testCustomScheme(): void
    {
        $provider = new TokenProvider(['token' => 'my-api-key', 'scheme' => 'Basic']);
        $this->assertSame('Basic my-api-key', $provider->getAuthorizationHeader());
    }

    public function testReturnsNullWhenTokenIsNull(): void
    {
        $provider = new TokenProvider();
        $this->assertNull($provider->getAuthorizationHeader());
    }

    public function testReturnsNullWhenTokenIsEmpty(): void
    {
        $provider = new TokenProvider(['token' => '']);
        $this->assertNull($provider->getAuthorizationHeader());
    }

    public function testNullAuthWhenNothingConfigured(): void
    {
        $provider = new TokenProvider();
        $this->assertNull($provider->getAuthorizationHeader());
        $this->assertNull($provider->getAuthorizationHeader());
    }
}
