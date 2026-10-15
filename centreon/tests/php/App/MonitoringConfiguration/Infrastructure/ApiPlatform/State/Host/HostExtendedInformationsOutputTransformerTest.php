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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostExtendedInformationsOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostIconOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * An all-empty block is left null rather than emitted as an all-null object, which the serializer
 * collapses to an invalid `[]`.
 */
final class HostExtendedInformationsOutputTransformerTest extends TestCase
{
    public function testItTransformsNoExtendedInformationsToNull(): void
    {
        self::assertNull($this->transformer()->transform(null));
    }

    public function testItTransformsAnAllEmptyBlockToNull(): void
    {
        self::assertNull($this->transformer()->transform(new ExtendedInformations(), ['icons' => []]));
    }

    public function testItTransformsABlockWhoseOnlyIconIsGoneToNull(): void
    {
        self::assertNull($this->transformer()->transform(new ExtendedInformations(iconId: new MediaId(1)), ['icons' => []]));
    }

    public function testItTransformsABlockWithAFieldSet(): void
    {
        $output = $this->transformer()->transform(new ExtendedInformations(note: 'rack 4'), ['icons' => []]);

        self::assertInstanceOf(HostExtendedInformationsOutput::class, $output);
        self::assertSame('rack 4', $output->note);
        self::assertNull($output->icon);
    }

    private function transformer(): HostExtendedInformationsOutputTransformer
    {
        return new HostExtendedInformationsOutputTransformer(new HostIconOutputTransformer(new MediaUrlGenerator('', new RequestStack())));
    }
}
