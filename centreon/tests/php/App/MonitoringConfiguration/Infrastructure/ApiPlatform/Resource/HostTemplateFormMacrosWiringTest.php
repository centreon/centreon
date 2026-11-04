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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Wiring of the host template form's inherited macros route. The macro resolution itself is pinned
 * by State/HostTemplate/GetHostTemplateMacrosProviderTest and is not repeated here.
 */
final class HostTemplateFormMacrosWiringTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    private const MESSAGE = 'You are not allowed to access host templates';

    // about 100 times a warm response (under 30 ms), so a loaded runner cannot flake
    private const MAX_SECONDS = 2.0;

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItRequiresAuthentication(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");

        $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenToAHostTemplateReadOnlyUser(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        $response = $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(403);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertSame(self::MESSAGE, $body['message']);
    }

    public function testItIsForbiddenToAHostReadWriteUserWithoutHostTemplateAccess(): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $username = bin2hex(random_bytes(8));
        $this->grantHostTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_WRITE, $this->tag);
        $this->login($username);

        // guard: the user does hold Hosts read-write
        $this->request('GET', "/api/configuration/hosts/host_templates/{$templateId}");
        self::assertResponseIsSuccessful();

        $response = $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(403);
        self::assertSame(self::MESSAGE, $response->toArray(false)['message']);
    }

    public function testItReturnsTheOwnAndInheritedMacrosToAHostTemplateReadWriteUserWithoutHostAccess(): void
    {
        $ancestorId = $this->insertHostTemplate("ancestor-{$this->tag}");
        $ancestorMacroId = $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10');
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->connection->insert('host_template_relation', ['host_host_id' => $templateId, 'host_tpl_id' => $ancestorId, '`order`' => 1]);
        $ownMacroId = $this->insertMacro($templateId, '$_HOSTPORT$', '443');
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        // guard: the user holds no Hosts right
        $this->request('GET', "/api/configuration/hosts/host_templates/{$templateId}");
        self::assertResponseStatusCodeSame(403);

        $response = $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertEqualsCanonicalizing(['@context', '@id', '@type', 'id', 'name', 'macros'], array_keys($body));
        self::assertSame($templateId, $body['id']);
        self::assertSame("tpl-{$this->tag}", $body['name']);
        /** @var list<array<string, mixed>> $macros */
        $macros = $body['macros'];
        self::assertSame([$ownMacroId, $ancestorMacroId], array_column($macros, 'id'));
        self::assertSame(['PORT', 'TIMEOUT'], array_column($macros, 'name'));
        self::assertArrayNotHasKey('parent', $macros[0]);
        self::assertSame('template', $macros[1]['parent']);
    }

    public function testItAnswersTheSameMacrosAsTheHostFormRoute(): void
    {
        $ancestorId = $this->insertHostTemplate("ancestor-{$this->tag}");
        $this->insertMacro($ancestorId, '$_HOSTTIMEOUT$', '10');
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->connection->insert('host_template_relation', ['host_host_id' => $templateId, 'host_tpl_id' => $ancestorId, '`order`' => 1]);
        $this->insertMacro($templateId, '$_HOSTPORT$', '443');
        $this->login();

        $hostFormBody = $this->request('GET', "/api/configuration/hosts/host_templates/{$templateId}")->toArray();
        $templateFormBody = $this->request('GET', $this->uri((string) $templateId))->toArray();

        self::assertCount(2, (array) $templateFormBody['macros']);
        self::assertSame($this->withoutGeneratedIds($hostFormBody['macros']), $this->withoutGeneratedIds($templateFormBody['macros']));
        self::assertSame($hostFormBody['id'], $templateFormBody['id']);
        self::assertSame($hostFormBody['name'], $templateFormBody['name']);
    }

    public function testItReturnsNotFoundForAnUnknownTemplate(): void
    {
        $this->login();

        $this->request('GET', $this->uri((string) PHP_INT_MAX));

        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForARegularHost(): void
    {
        $this->connection->insert('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        $this->login();

        $this->request('GET', $this->uri((string) $hostId));

        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundForAnInactiveTemplate(): void
    {
        $templateId = $this->insertHostTemplate("inactive-{$this->tag}", active: false);
        $this->login();

        $this->request('GET', $this->uri((string) $templateId));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * A loop is refused on save, but older data may hold one: the route must still answer, at once,
     * for a template in the loop and for a template reaching it through its parent.
     */
    public function testItAnswersPromptlyOnALoopAlreadyInTheData(): void
    {
        $first = $this->insertHostTemplate("loop-first-{$this->tag}");
        $second = $this->insertHostTemplate("loop-second-{$this->tag}");
        $child = $this->insertHostTemplate("loop-child-{$this->tag}");
        foreach ([[$first, $second], [$second, $first], [$child, $first]] as [$inheriting, $inherited]) {
            $this->connection->insert('host_template_relation', ['host_host_id' => $inheriting, 'host_tpl_id' => $inherited, '`order`' => 1]);
        }
        $firstMacroId = $this->insertMacro($first, '$_HOSTFIRST$', '1');
        $secondMacroId = $this->insertMacro($second, '$_HOSTSECOND$', '2');
        $this->login();

        [$seconds, $firstMacros] = $this->timedMacros($first);
        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame([$firstMacroId, $secondMacroId], array_column($firstMacros, 'id'));
        self::assertArrayNotHasKey('parent', $firstMacros[0]);
        self::assertSame('template', $firstMacros[1]['parent']);

        [$seconds, $childMacros] = $this->timedMacros($child);
        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame([$firstMacroId, $secondMacroId], array_column($childMacros, 'id'));
        self::assertSame(['template', 'template'], array_column($childMacros, 'parent'));
    }

    /**
     * "0<id>" names an existing template once the leading zero is dropped: only the id requirement
     * keeps it unrouted.
     */
    #[DataProvider('provideInvalidIds')]
    public function testAnInvalidIdIsNotRouted(string $invalidId): void
    {
        $templateId = $this->insertHostTemplate("tpl-{$this->tag}");
        $this->login();

        $this->request('GET', $this->uri(str_replace('<id>', (string) $templateId, $invalidId)));

        self::assertResponseStatusCodeSame(404);
    }

    public function testItIsDocumentedWithItsErrorResponses(): void
    {
        /** @var OpenApiFactoryInterface $openApiFactory */
        $openApiFactory = self::getContainer()->get('api_platform.openapi.factory');
        $pathItem = $openApiFactory()->getPaths()->getPath($this->uri('{id}'));

        self::assertInstanceOf(PathItem::class, $pathItem);
        $operation = $pathItem->getGet();
        self::assertInstanceOf(Operation::class, $operation);
        $responses = $operation->getResponses() ?? [];
        foreach ([403 => self::MESSAGE, 404 => 'Host template not found'] as $status => $description) {
            $response = $responses[$status] ?? null;
            self::assertInstanceOf(Response::class, $response);
            self::assertSame($description, $response->getDescription());
        }
    }

    public function testThePathIsRoutedToTheMacrosOperationOnly(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertSame(
            '_api_/configuration/host_templates/host_templates/{id}/macros_get',
            $router->match($this->uri('5'))['_route'] ?? null,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidIds(): iterable
    {
        yield 'zero' => ['0'];

        yield 'leading zero' => ['0<id>'];

        yield 'not a number' => ['not-a-number'];
    }

    /**
     * The first requests of a test process are slow whatever their query: two warm-ups, then the
     * timed request.
     *
     * @return array{float, list<array<string, mixed>>}
     */
    private function timedMacros(int $templateId): array
    {
        $this->request('GET', $this->uri((string) $templateId));
        $this->request('GET', $this->uri((string) $templateId));

        $start = hrtime(true);
        $response = $this->request('GET', $this->uri((string) $templateId));
        $seconds = (hrtime(true) - $start) / 1e9;
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $macros */
        $macros = $response->toArray()['macros'];

        return [$seconds, $macros];
    }

    private function uri(string $id): string
    {
        return "/api/configuration/host_templates/host_templates/{$id}/macros";
    }

    /**
     * Each macro's "@id" is a blank-node id generated per response.
     *
     * @return list<array<array-key, mixed>>
     */
    private function withoutGeneratedIds(mixed $macros): array
    {
        self::assertIsArray($macros);

        return array_values(array_map(
            static function (mixed $macro): array {
                self::assertIsArray($macro);
                unset($macro['@id']);

                return $macro;
            },
            $macros,
        ));
    }

    private function insertHostTemplate(string $name, bool $active = true): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
            'host_activate' => $active ? '1' : '0',
            'host_locked' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMacro(int $hostId, string $name, string $value): int
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'host_host_id' => $hostId,
            'macro_order' => 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
