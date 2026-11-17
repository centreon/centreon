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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Validator;

use App\MonitoringConfiguration\Infrastructure\Validator\NoControlCharacters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class NoControlCharactersTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function acceptedValueProvider(): array
    {
        return [
            'text with spaces (0x20, right after the range)' => ['a free-text note'],
            'tilde (0x7E, right before DEL)' => ['first~second'],
            'non-ASCII text' => ['hôte réseau'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedValueProvider(): array
    {
        return [
            'line feed' => ["first\nsecond"],
            'carriage return' => ["first\rsecond"],
            'tab' => ["first\tsecond"],
            'null byte (0x00, start of the range)' => ["first\0second"],
            'unit separator (0x1F, end of the range)' => ["first\x1Fsecond"],
            'delete (0x7F)' => ["first\x7Fsecond"],
        ];
    }

    #[DataProvider('acceptedValueProvider')]
    public function testItAcceptsAValueWithoutControlCharacter(string $value): void
    {
        self::assertSame([], $this->messages($value));
    }

    #[DataProvider('rejectedValueProvider')]
    public function testItRejectsAValueWithAControlCharacter(string $value): void
    {
        self::assertSame([NoControlCharacters::MESSAGE], $this->messages($value));
    }

    /**
     * @return list<string>
     */
    private function messages(string $value): array
    {
        $messages = [];
        foreach (Validation::createValidator()->validate($value, new NoControlCharacters()) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
