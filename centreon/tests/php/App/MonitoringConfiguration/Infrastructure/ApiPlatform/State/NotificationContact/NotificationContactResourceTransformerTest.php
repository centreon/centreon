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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\NotificationContact;

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContact;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\NotificationContact\NotificationContactResourceTransformer;
use PHPUnit\Framework\TestCase;

final class NotificationContactResourceTransformerTest extends TestCase
{
    public function testTransformMapsTheAggregateToTheResource(): void
    {
        $resource = (new NotificationContactResourceTransformer())->transform(
            new NotificationContact(new NotificationContactId(42), new NotificationContactName('John Doe'))
        );

        self::assertSame(42, $resource->id);
        self::assertSame('John Doe', $resource->name);
    }
}
