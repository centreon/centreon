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

namespace Tests\App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\OpenApi;
use App\Shared\Infrastructure\ApiPlatform\LegacyErrorOpenApiFactory;
use PHPUnit\Framework\TestCase;

final class LegacyErrorOpenApiFactoryTest extends TestCase
{
    public function testItDocumentsTheLegacyBodyOnErrorResponsesWithoutContent(): void
    {
        $responses = $this->decorate(new Model\Operation(responses: [
            '403' => new Model\Response('Forbidden'),
            404 => new Model\Response('Not found'),
        ]))->getPaths()->getPath('/resources')?->getGet()?->getResponses() ?? [];

        foreach (['403', 404] as $status) {
            $content = $responses[$status]->getContent();
            self::assertNotNull($content);
            self::assertSame(
                '#/components/schemas/' . LegacyErrorOpenApiFactory::SCHEMA_NAME,
                $content['application/json']->getSchema()['$ref'],
            );
        }
    }

    public function testItLeavesSuccessAndAlreadyDocumentedResponsesAlone(): void
    {
        $documented = new Model\Response('Invalid input', new \ArrayObject([
            'application/problem+json' => new Model\MediaType(new \ArrayObject(['type' => 'object'])),
        ]));
        $responses = $this->decorate(new Model\Operation(responses: [
            '204' => new Model\Response('Done'),
            '422' => $documented,
        ]))->getPaths()->getPath('/resources')?->getGet()?->getResponses() ?? [];

        self::assertNull($responses['204']->getContent());
        self::assertSame($documented, $responses['422']);
    }

    public function testItRegistersTheLegacyErrorSchema(): void
    {
        $schemas = $this->decorate(new Model\Operation())->getComponents()->getSchemas();

        self::assertNotNull($schemas);
        self::assertSame(
            ['code', 'message'],
            $schemas[LegacyErrorOpenApiFactory::SCHEMA_NAME]['required'],
        );
    }

    private function decorate(Model\Operation $operation): OpenApi
    {
        $paths = new Model\Paths();
        $paths->addPath('/resources', new Model\PathItem(get: $operation));
        $openApi = new OpenApi(new Model\Info('Centreon', '1.0'), [], $paths, new Model\Components());

        $inner = $this->createMock(OpenApiFactoryInterface::class);
        $inner->method('__invoke')->willReturn($openApi);

        return (new LegacyErrorOpenApiFactory($inner))();
    }
}
