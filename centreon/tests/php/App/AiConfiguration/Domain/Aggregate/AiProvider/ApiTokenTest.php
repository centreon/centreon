<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    public function testAPlainTokenCanNotBeAVaultReference(): void
    {
        // otherwise a user could make the platform send the secret stored at that path
        $this->expectException(\InvalidArgumentException::class);

        new ApiToken('secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN');
    }

    public function testATooLongTokenIsNotInTheErrorMessage(): void
    {
        $token = str_repeat('k', 4097);

        foreach ([static fn (): ApiToken => new ApiToken($token), static fn (): StoredApiToken => new StoredApiToken($token)] as $build) {
            try {
                $build();
                self::fail('A too long token must be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringNotContainsString($token, $exception->getMessage());
            }
        }
    }

    public function testAStoredPlainToken(): void
    {
        $stored = StoredApiToken::plain(new ApiToken('a-token'));

        self::assertFalse($stored->isVaultReference());
        self::assertSame('a-token', $stored->plainToken()->value);
    }

    public function testAStoredVaultReference(): void
    {
        $stored = StoredApiToken::vaultReference('secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN');

        self::assertTrue($stored->isVaultReference());
    }

    public function testAVaultReferenceIsNotAPlainToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new StoredApiToken('secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN')->plainToken();
    }

    public function testAVaultReferenceMustHaveThePrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StoredApiToken::vaultReference('a-token');
    }
}
