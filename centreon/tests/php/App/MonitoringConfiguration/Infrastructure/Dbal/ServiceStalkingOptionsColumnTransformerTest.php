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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceStalkingOptionsColumnTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceStalkingOptionsColumnTransformerTest extends TestCase
{
    /**
     * @param list<ServiceStalkingOptionEnum> $options
     */
    #[DataProvider('optionsProvider')]
    public function testItWritesEachOptionAsItsEngineLetter(array $options, ?string $expected): void
    {
        self::assertSame($expected, (new ServiceStalkingOptionsColumnTransformer())->transform($options));
    }

    /**
     * @return iterable<string, array{list<ServiceStalkingOptionEnum>, ?string}>
     */
    public static function optionsProvider(): iterable
    {
        yield 'no option' => [[], null];

        yield 'ok' => [[ServiceStalkingOptionEnum::Ok], 'o'];

        yield 'warning' => [[ServiceStalkingOptionEnum::Warning], 'w'];

        yield 'unknown' => [[ServiceStalkingOptionEnum::Unknown], 'u'];

        yield 'critical' => [[ServiceStalkingOptionEnum::Critical], 'c'];

        yield 'every option' => [
            [
                ServiceStalkingOptionEnum::Ok,
                ServiceStalkingOptionEnum::Warning,
                ServiceStalkingOptionEnum::Unknown,
                ServiceStalkingOptionEnum::Critical,
            ],
            'o,w,u,c',
        ];
    }
}
