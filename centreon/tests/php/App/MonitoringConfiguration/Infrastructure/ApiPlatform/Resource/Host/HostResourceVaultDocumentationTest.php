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
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use PHPUnit\Framework\TestCase;

/**
 * Both updates write the host's own secrets to the vault before saving it, and answer HTTP 502 when it
 * cannot be reached.
 */
final class HostResourceVaultDocumentationTest extends TestCase
{
    public function testPutAndPatchDocumentTheVault502(): void
    {
        foreach ([Put::class, Patch::class] as $operationClass) {
            $responses = $this->documentedResponses($operationClass);

            self::assertArrayHasKey(502, $responses, $operationClass);
            $response = $responses[502];
            \assert($response instanceof Response);
            self::assertSame('The vault could not be reached', $response->getDescription(), $operationClass);
        }
    }

    /**
     * @param class-string<HttpOperation> $operationClass
     *
     * @return array<int|string, mixed>
     */
    private function documentedResponses(string $operationClass): array
    {
        $attributes = (new \ReflectionClass(HostResource::class))->getAttributes(ApiResource::class);
        self::assertCount(1, $attributes);

        foreach ($attributes[0]->newInstance()->getOperations() ?? [] as $operation) {
            if ($operation instanceof $operationClass && $operation->getUriTemplate() === '/configuration/hosts/{id}') {
                $openApi = $operation->getOpenapi();
                \assert($openApi instanceof OpenApiOperation);

                return $openApi->getResponses() ?? [];
            }
        }

        self::fail(sprintf('The %s /configuration/hosts/{id} operation was not found on HostResource.', $operationClass));
    }
}
