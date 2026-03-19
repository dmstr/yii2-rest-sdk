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

    public function testReturnsStaticToken(): void
    {
        $provider = new TokenProvider(['token' => 'my-api-key']);
        $this->assertSame('my-api-key', $provider->getAccessToken());
    }

    public function testReturnsNullWhenTokenIsNull(): void
    {
        $provider = new TokenProvider();
        $this->assertNull($provider->getAccessToken());
    }

    public function testReturnsNullWhenTokenIsEmpty(): void
    {
        $provider = new TokenProvider(['token' => '']);
        $this->assertNull($provider->getAccessToken());
    }

    public function testNullAuthWhenNothingConfigured(): void
    {
        $provider = new TokenProvider();
        $this->assertNull($provider->getAccessToken());
        $this->assertNull($provider->getAccessToken());
    }
}
