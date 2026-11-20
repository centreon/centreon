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

namespace Tests\App\AiConfiguration\Infrastructure\ApiPlatform;

use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\DiscoverModelsInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\TestConnectionInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\AiProviderResource;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\CreateAiProviderResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * A submitted API token is never a vault reference: the token of a stored provider is read back
 * from the vault, so accepting a reference would let a user point the token at another secret.
 */
final class ApiTokenInputValidationTest extends TestCase
{
    #[DataProvider('inputProvider')]
    public function testAVaultReferenceIsRejected(string $inputClass): void
    {
        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validatePropertyValue($inputClass, 'apiToken', 'secret::vault::monitoring/hosts/abc-123::_HOSTSNMPCOMMUNITY');

        self::assertCount(1, $violations);
        self::assertSame('The API token cannot be a vault reference.', $violations->get(0)->getMessage());
    }

    #[DataProvider('inputProvider')]
    public function testAPlainTokenIsAccepted(string $inputClass): void
    {
        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validatePropertyValue($inputClass, 'apiToken', 'sk-a-plain-token');

        self::assertCount(0, $violations);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function inputProvider(): iterable
    {
        yield 'create' => [CreateAiProviderResource::class];

        yield 'update' => [AiProviderResource::class];

        yield 'test connection' => [TestConnectionInput::class];

        yield 'discover models' => [DiscoverModelsInput::class];
    }
}
