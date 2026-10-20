<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Documents the `{code, message}` body that LegacyHttpExceptionListener returns for errors,
 * on every error response declared without content.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class LegacyErrorOpenApiFactory implements OpenApiFactoryInterface
{
    public const SCHEMA_NAME = 'LegacyError';

    private const METHODS = ['Get', 'Put', 'Post', 'Delete', 'Patch'];

    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $paths = new Model\Paths();
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            foreach (self::METHODS as $method) {
                $operation = $pathItem->{'get' . $method}();
                if ($operation instanceof Model\Operation) {
                    $pathItem = $pathItem->{'with' . $method}($this->documentErrors($operation));
                }
            }
            $paths->addPath((string) $path, $pathItem);
        }

        $components = $openApi->getComponents();
        $schemas = $components->getSchemas() ?? new \ArrayObject();
        if (! $schemas->offsetExists(self::SCHEMA_NAME)) {
            $schemas[self::SCHEMA_NAME] = new \ArrayObject([
                'type' => 'object',
                'description' => 'Error returned by the API.',
                'required' => ['code', 'message'],
                'properties' => [
                    'code' => ['type' => 'integer', 'example' => 0],
                    'message' => ['type' => 'string', 'example' => 'Resource not found'],
                ],
            ]);
        }

        return $openApi->withPaths($paths)->withComponents($components->withSchemas($schemas));
    }

    private function documentErrors(Model\Operation $operation): Model\Operation
    {
        foreach ($operation->getResponses() ?? [] as $status => $response) {
            if (
                (int) $status < 400
                || ! $response instanceof Model\Response
                || ($response->getContent()?->count() ?? 0) > 0
            ) {
                continue;
            }
            $operation = $operation->withResponse($status, $response->withContent(new \ArrayObject([
                'application/json' => new Model\MediaType(
                    new \ArrayObject(['$ref' => '#/components/schemas/' . self::SCHEMA_NAME]),
                ),
            ])));
        }

        return $operation;
    }
}
