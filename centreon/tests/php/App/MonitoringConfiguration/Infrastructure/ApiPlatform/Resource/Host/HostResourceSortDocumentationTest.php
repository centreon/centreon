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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use PHPUnit\Framework\TestCase;

/**
 * An attribute only takes constant expressions, so the sort parameter of the hosts listing cannot be
 * generated from the sortable fields of HostCriteria: this test keeps the OpenAPI documentation aligned
 * with them instead.
 */
final class HostResourceSortDocumentationTest extends TestCase
{
    public function testTheDocumentedSortParametersAreTheSortableFieldsOfTheCriteria(): void
    {
        $documented = [];
        foreach ($this->hostListingParameters() as $parameter) {
            if ($parameter->getName() === 'sort') {
                /** @var array{properties?: array<string, mixed>} $schema */
                $schema = $parameter->getSchema();
                $documented = array_keys($schema['properties'] ?? []);

                break;
            }
        }

        $sortable = array_keys((new HostCriteria())->getFieldMapping());

        sort($documented);
        sort($sortable);
        self::assertSame($sortable, $documented);
    }

    /**
     * @return list<Parameter>
     */
    private function hostListingParameters(): array
    {
        $attributes = (new \ReflectionClass(HostResource::class))->getAttributes(ApiResource::class);
        self::assertCount(1, $attributes);

        foreach ($attributes[0]->newInstance()->getOperations() ?? [] as $operation) {
            if ($operation instanceof GetCollection && $operation->getUriTemplate() === '/configuration/hosts') {
                $openApi = $operation->getOpenapi();
                \assert($openApi instanceof OpenApiOperation);

                return array_values($openApi->getParameters() ?? []);
            }
        }

        self::fail('The GET /configuration/hosts operation was not found on HostResource.');
    }
}
