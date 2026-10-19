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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use PHPUnit\Framework\TestCase;

final class HostResourceTest extends TestCase
{
    public function testSortDocumentationExposesTheFieldsOfTheCriteria(): void
    {
        $attributes = (new \ReflectionClass(HostResource::class))->getAttributes(ApiResource::class);
        $resource = $attributes[0]->newInstance();

        $sortParameter = null;
        foreach ($resource->getOperations() ?? [] as $operation) {
            if (! $operation instanceof GetCollection || $operation->getUriTemplate() !== '/configuration/hosts') {
                continue;
            }
            foreach ($operation->getOpenapi()->getParameters() as $parameter) {
                if ($parameter->getName() === 'sort') {
                    $sortParameter = $parameter;
                }
            }
        }

        self::assertNotNull($sortParameter, 'The hosts listing must document its "sort" parameter.');
        self::assertEqualsCanonicalizing(
            array_keys((new HostCriteria())->getFieldMapping()),
            array_keys($sortParameter->getSchema()['properties'])
        );
    }
}
