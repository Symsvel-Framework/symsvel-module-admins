<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Setup\BootstrapPayload;

final class BootstrapPayloadTest extends TestCase
{
    public function testAcceptsAndNormalizesValidCredentials(): void
    {
        $payload = (new BootstrapPayload())->parse(json_encode([
            'email' => ' developer@example.test ',
            'password' => 'A-long-secret-password-1!',
        ], JSON_THROW_ON_ERROR));
        self::assertSame('developer@example.test', $payload['email']);
        self::assertSame('A-long-secret-password-1!', $payload['password']);
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidCredentials(string $raw): void
    {
        $this->expectException(\Throwable::class);
        (new BootstrapPayload())->parse($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid JSON' => ['{'];
        yield 'JSON scalar' => ['true'];
        yield 'missing password' => ['{"email":"dev@example.test"}'];
        yield 'bad email' => ['{"email":"invalid","password":"long-enough-password"}'];
        yield 'missing uppercase' => ['{"email":"dev@example.test","password":"long-password-123!"}'];
        yield 'short password' => ['{"email":"dev@example.test","password":"short"}'];
        yield 'null byte' => [json_encode(['email' => 'dev@example.test', 'password' => "long-password\0x"], JSON_THROW_ON_ERROR)];
        yield 'oversized payload' => [str_repeat('x', 8192)];
        yield 'oversized password' => [json_encode(['email' => 'dev@example.test', 'password' => 'aB9!'.str_repeat('x', 1021)], JSON_THROW_ON_ERROR)];
    }
}
