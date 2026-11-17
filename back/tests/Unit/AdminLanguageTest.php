<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Entity\Admin;

final class AdminLanguageTest extends TestCase
{
    public function testIsoCodeNormalizationAndOptionalPreference(): void
    {
        $account = new Admin('demo@example.org', password_hash('safe-password-for-test', PASSWORD_ARGON2ID));
        self::assertNull($account->getLanguage());
        $account->setLanguage(' FR ');
        self::assertSame('fr', $account->getLanguage());
        $account->setLanguage(null);
        self::assertNull($account->getLanguage());
    }

    public function testInvalidCodeIsRejected(): void
    {
        $account = new Admin('demo@example.org', password_hash('safe-password-for-test', PASSWORD_ARGON2ID));
        $this->expectException(\InvalidArgumentException::class);
        $account->setLanguage('fr-FR');
    }
}
