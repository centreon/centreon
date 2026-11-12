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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use PHPUnit\Framework\TestCase;

final class ServiceMacroTransformerTest extends TestCase
{
    public function testItNeverEchoesAPasswordValue(): void
    {
        $output = (new ServiceMacroTransformer())->transform(new ServiceMacro(
            new ServiceMacroName('pwd'),
            'secret::vault::monitoring/services/uuid::_SERVICEPWD',
            isPassword: true,
            id: new ServiceMacroId(3),
            parent: ServiceMacroParentEnum::Template,
        ));

        self::assertSame(3, $output->id);
        self::assertSame('PWD', $output->name);
        self::assertNull($output->value);
        self::assertTrue($output->isPassword);
        self::assertSame('template', $output->parent);
    }

    public function testAnOwnMacroHasItsValueAndNoParent(): void
    {
        $output = (new ServiceMacroTransformer())->transform(
            new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, description: 'not exposed', id: new ServiceMacroId(4)),
        );

        self::assertSame(4, $output->id);
        self::assertSame('bar', $output->value);
        self::assertFalse($output->isPassword);
        self::assertNull($output->parent);
    }
}
