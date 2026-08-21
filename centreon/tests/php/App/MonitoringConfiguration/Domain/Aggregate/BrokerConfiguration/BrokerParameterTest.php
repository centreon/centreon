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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerParameter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrokerParameterTest extends TestCase
{
    public function testConstructWithMinimalArguments(): void
    {
        $parameter = new BrokerParameter('name', 'central-module-master-output');

        self::assertSame('name', $parameter->configKey);
        self::assertSame('central-module-master-output', $parameter->configValue);
        self::assertSame(0, $parameter->groupLevel);
        self::assertNull($parameter->subGroupId);
        self::assertNull($parameter->parentGroupId);
        self::assertNull($parameter->fieldIndex);
    }

    public function testConstructWithAllArguments(): void
    {
        $parameter = new BrokerParameter(
            configKey: 'host',
            configValue: '127.0.0.1',
            groupLevel: 2,
            subGroupId: 3,
            parentGroupId: 1,
            fieldIndex: 4,
        );

        self::assertSame('host', $parameter->configKey);
        self::assertSame('127.0.0.1', $parameter->configValue);
        self::assertSame(2, $parameter->groupLevel);
        self::assertSame(3, $parameter->subGroupId);
        self::assertSame(1, $parameter->parentGroupId);
        self::assertSame(4, $parameter->fieldIndex);
    }

    public function testConstructAllowsEmptyConfigValue(): void
    {
        $parameter = new BrokerParameter('failover', '');

        self::assertSame('', $parameter->configValue);
    }

    public function testConstructAllowsConfigKeyAtMaxLength(): void
    {
        $configKey = str_repeat('a', 50);

        $parameter = new BrokerParameter($configKey, 'value');

        self::assertSame($configKey, $parameter->configKey);
    }

    public function testConstructAllowsConfigValueAtMaxLength(): void
    {
        $configValue = str_repeat('a', 255);

        $parameter = new BrokerParameter('key', $configValue);

        self::assertSame($configValue, $parameter->configValue);
    }

    /**
     * @param array{
     *     configKey: string,
     *     configValue: string,
     *     groupLevel?: int,
     *     subGroupId?: int|null,
     *     parentGroupId?: int|null,
     *     fieldIndex?: int|null
     * } $arguments
     */
    #[DataProvider('invalidArgumentsProvider')]
    public function testConstructRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BrokerParameter(...$arguments);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidArgumentsProvider(): iterable
    {
        yield 'empty config key' => [['configKey' => '', 'configValue' => 'value']];

        yield 'config key too long' => [['configKey' => str_repeat('a', 51), 'configValue' => 'value']];

        yield 'config value too long' => [['configKey' => 'key', 'configValue' => str_repeat('a', 256)]];

        yield 'negative group level' => [['configKey' => 'key', 'configValue' => 'value', 'groupLevel' => -1]];

        yield 'negative subgroup id' => [['configKey' => 'key', 'configValue' => 'value', 'subGroupId' => -1]];

        yield 'negative parent group id' => [['configKey' => 'key', 'configValue' => 'value', 'parentGroupId' => -1]];

        yield 'negative field index' => [['configKey' => 'key', 'configValue' => 'value', 'fieldIndex' => -1]];
    }
}
