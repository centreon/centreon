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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\MonitoringConfiguration\Application\Service\HostLicenseLimit;
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\HostTemplateChoicesTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\ListHostTemplatesChoicesProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;

/**
 * The exclude_template_id parameter: its parsing and the ids it adds to the excluded ones. The
 * descendants query itself is pinned by DbalHostTemplateRepositoryDescendantsTest.
 */
final class ListHostTemplatesChoicesProviderExclusionTest extends TestCase
{
    private FakeHostTemplateRepository $repository;

    private ListHostTemplatesChoicesProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new FakeHostTemplateRepository();
        // without the license manager module, the license host limit counts as reached
        $this->provider = new ListHostTemplatesChoicesProvider(
            new HostTemplateChoicesTransformer(),
            $this->repository,
            new Pagination(),
            new UnlicensedHostTemplates(new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()), $this->repository),
        );
    }

    public function testItExcludesTheTemplateAndItsDescendants(): void
    {
        $this->repository->descendantIds = [5 => [6, 7, 9]];

        $this->provide(['exclude_template_id' => '5']);

        self::assertSame([5, 6, 7, 9], $this->sortedExcludedIds());
    }

    public function testItExcludesAnUnknownTemplateIdAloneWithoutFailing(): void
    {
        $this->provide(['exclude_template_id' => '404']);

        self::assertSame([404], $this->sortedExcludedIds());
    }

    public function testItKeepsTheLicenseExclusionNextToTheDescendants(): void
    {
        $this->repository->monitoringConnectorTemplateIds = [2, 3];
        $this->repository->descendantIds = [5 => [6]];

        $this->provide(['exclude_template_id' => '5']);

        self::assertSame([2, 3, 5, 6], $this->sortedExcludedIds());
    }

    public function testWithoutTheParameterOnlyTheLicenseExclusionApplies(): void
    {
        $this->repository->monitoringConnectorTemplateIds = [2];
        $this->repository->descendantIds = [5 => [6]];

        $this->provide([]);

        self::assertSame([2], $this->sortedExcludedIds());
    }

    /**
     * An operation that does not opt in neither reads nor validates the parameter.
     */
    #[DataProvider('provideValuesForAnOperationWithoutTheOptIn')]
    public function testAnOperationWithoutTheOptInIgnoresTheParameter(string $value): void
    {
        $this->repository->descendantIds = [5 => [6]];

        $this->provide(['exclude_template_id' => $value], optIn: false);

        self::assertSame([], $this->sortedExcludedIds());
    }

    #[DataProvider('provideInvalidValues')]
    public function testAnInvalidValueIsABadRequest(mixed $value): void
    {
        try {
            $this->provide(['exclude_template_id' => $value]);
            self::fail('An invalid exclude_template_id must be rejected.');
        } catch (BadRequestHttpException $exception) {
            self::assertSame('The "exclude_template_id" filter must be a positive integer.', $exception->getMessage());
        }
        self::assertNull($this->repository->receivedCriteria, 'nothing is listed for a rejected request');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValuesForAnOperationWithoutTheOptIn(): iterable
    {
        yield 'valid' => ['5'];

        yield 'invalid' => ['abc'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'zero' => ['0'];

        yield 'negative' => ['-1'];

        yield 'letters' => ['abc'];

        yield 'empty' => [''];

        yield 'decimal' => ['1.5'];

        yield 'exponent' => ['1e3'];

        yield 'leading zero' => ['007'];

        yield 'leading space' => [' 5'];

        yield 'trailing newline' => ["5\n"];

        yield 'plus sign' => ['+5'];

        yield 'beyond PHP_INT_MAX' => ['99999999999999999999'];

        yield 'array' => [['5']];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function provide(array $filters, bool $optIn = true): void
    {
        $operation = new GetCollection(extraProperties: $optIn ? ['exclude_template_id' => true] : []);
        $this->provider->provide($operation, [], ['filters' => $filters]);
    }

    /**
     * @return list<int>
     */
    private function sortedExcludedIds(): array
    {
        self::assertInstanceOf(HostTemplateCriteria::class, $this->repository->receivedCriteria);
        $ids = $this->repository->receivedCriteria->getExcludedIds();
        sort($ids);

        return $ids;
    }
}
