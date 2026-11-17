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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceNameException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceNameTest extends TestCase
{
    public function testItTrimsSurroundingWhitespace(): void
    {
        $name = new ServiceName('  my-service  ');

        self::assertSame('my-service', $name->value);
    }

    public function testItRejectsAWhitespaceOnlyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceName('   ');
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceName('');
    }

    public function testItRejectsANameLongerThan200Characters(): void
    {
        // The size of the service_description column.
        $this->expectException(\InvalidArgumentException::class);

        new ServiceName(str_repeat('a', 201));
    }

    public function testItAcceptsANameAtThe200CharacterLimit(): void
    {
        $name = new ServiceName(str_repeat('é', 200));

        self::assertSame(200, mb_strlen($name->value));
    }

    #[DataProvider('userInputProvider')]
    public function testItNormalizesAUserInput(string $input, string $expectedName): void
    {
        self::assertSame($expectedName, ServiceName::fromUserInput($input)->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function userInputProvider(): iterable
    {
        yield 'inner and outer runs of spaces' => ['  a  b ', 'a b'];

        yield '200 characters' => [str_repeat('a', 200), str_repeat('a', 200)];

        yield '200 multibyte characters' => [str_repeat('é', 200), str_repeat('é', 200)];

        yield '200 characters once collapsed' => [str_repeat('a', 198) . '   b', str_repeat('a', 198) . ' b'];
    }

    #[DataProvider('invalidUserInputProvider')]
    public function testItRefusesAnInvalidUserInput(string $input, string $expectedReason): void
    {
        try {
            ServiceName::fromUserInput($input);
            self::fail('The name must be refused.');
        } catch (InvalidServiceNameException $exception) {
            self::assertSame($expectedReason, $exception->reason);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUserInputProvider(): iterable
    {
        yield 'empty' => ['', 'is empty'];

        yield 'whitespace only' => [" \t\n ", 'is empty'];

        yield '201 characters' => [str_repeat('a', 201), 'is longer than 200 characters'];
        foreach (mb_str_split('~!$%^&*"|\'<>?,()=') as $character) {
            yield "forbidden {$character}" => ["Pi{$character}ng", 'contains a forbidden character (~ ! $ % ^ & * " | \' < > ? , ( ) =)'];
        }

        yield 'single inner tab' => ["Pi\tng", 'contains a control character'];

        // Collapsed into one space, it would pass: the check runs on the raw value.
        yield 'run of tabs' => ["a\t\tb", 'contains a control character'];

        yield 'trailing line break' => ["Ping\n", 'contains a control character'];

        yield 'form feed at an end' => [" \fPing", 'contains a control character'];

        yield 'inner null byte' => ["Pi\0ng", 'contains a control character'];

        yield 'delete character' => ["Ping\x7F", 'contains a control character'];
    }
}
