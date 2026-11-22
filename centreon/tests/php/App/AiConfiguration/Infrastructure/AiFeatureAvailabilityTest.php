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

namespace Tests\App\AiConfiguration\Infrastructure;

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use PHPUnit\Framework\TestCase;

final class AiFeatureAvailabilityTest extends TestCase
{
    public function testAvailableOnPremise(): void
    {
        (new AiFeatureAvailability(isCloudPlatform: false))->assertAvailable();

        $this->addToAssertionCount(1);
    }

    public function testDeniedOnACloudPlatform(): void
    {
        try {
            (new AiFeatureAvailability(isCloudPlatform: true))->assertAvailable();
            self::fail('Expected the AI provider configurations to be denied on a cloud platform');
        } catch (AccessDeniedException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }
}
