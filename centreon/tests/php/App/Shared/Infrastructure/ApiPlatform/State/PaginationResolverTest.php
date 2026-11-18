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

namespace Tests\App\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination as ApiPlatformPagination;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\ApiPlatform\State\PaginationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\App\Shared\Double\FakeOptionRepository;

final class PaginationResolverTest extends TestCase
{
    private FakeOptionRepository $options;

    private PaginationResolver $resolver;

    protected function setUp(): void
    {
        $this->options = new FakeOptionRepository();
        // client_items_per_page mirrors "pagination_client_items_per_page: true" in config.new/packages/api_platform.yaml
        $this->resolver = new PaginationResolver(
            new ApiPlatformPagination(['client_items_per_page' => true]),
            $this->options,
            fallbackItemsPerPage: 30,
        );
    }

    public function testItReturnsNothingWhenThePaginationIsDisabled(): void
    {
        self::assertNull($this->resolver->resolve(new GetCollection(paginationEnabled: false), ['filters' => []]));
    }

    public function testItUsesTheRequestedPageAndSize(): void
    {
        self::assertEquals(new Pagination(3, 40), $this->resolve(['page' => '3', 'itemsPerPage' => '40']));
    }

    public function testItCapsTheRequestedSizeAtTheMaximum(): void
    {
        self::assertEquals(new Pagination(1, 100), $this->resolve(['itemsPerPage' => '250']));
    }

    public function testARequestedSizeDoesNotReadThePlatformSetting(): void
    {
        $this->options->throws = true;

        self::assertEquals(new Pagination(1, 10), $this->resolve(['itemsPerPage' => '10']));
    }

    public function testItDefaultsToThePlatformSetting(): void
    {
        $this->options->options['maxViewConfiguration'] = '15';

        self::assertEquals(new Pagination(1, 15), $this->resolve([]));
    }

    public function testItFallsBackWhenThePlatformSettingIsMissing(): void
    {
        self::assertEquals(new Pagination(1, 30), $this->resolve([]));
    }

    public function testItCapsThePlatformSettingAtTheMaximum(): void
    {
        $this->options->options['maxViewConfiguration'] = '500';

        self::assertEquals(new Pagination(1, 100), $this->resolve([]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSettingProvider(): iterable
    {
        yield 'zero' => ['0'];

        yield 'negative' => ['-5'];

        yield 'decimal' => ['12.5'];

        yield 'not a number' => ['abc'];
    }

    #[DataProvider('invalidSettingProvider')]
    public function testItFallsBackWhenThePlatformSettingIsInvalid(string $setting): void
    {
        $this->options->options['maxViewConfiguration'] = $setting;

        self::assertEquals(new Pagination(1, 30), $this->resolve([]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidQueryProvider(): iterable
    {
        yield 'page zero' => [['page' => '0'], 'The "page" parameter must be a positive integer.'];

        yield 'negative page' => [['page' => '-1'], 'The "page" parameter must be a positive integer.'];

        yield 'non-numeric page' => [['page' => 'abc'], 'The "page" parameter must be a positive integer.'];

        yield 'decimal page' => [['page' => '1.5'], 'The "page" parameter must be a positive integer.'];

        yield 'empty page' => [['page' => ''], 'The "page" parameter must be a positive integer.'];

        yield 'array page' => [['page' => ['1']], 'The "page" parameter must be a positive integer.'];

        yield 'page beyond an int' => [['page' => '99999999999999999999'], 'The "page" parameter must be a positive integer.'];

        yield 'zero size' => [['itemsPerPage' => '0'], 'The "itemsPerPage" parameter must be a positive integer.'];

        yield 'negative size' => [['itemsPerPage' => '-1'], 'The "itemsPerPage" parameter must be a positive integer.'];

        yield 'non-numeric size' => [['itemsPerPage' => 'abc'], 'The "itemsPerPage" parameter must be a positive integer.'];
    }

    /**
     * @param array<string, mixed> $filters
     */
    #[DataProvider('invalidQueryProvider')]
    public function testItRejectsAnInvalidQuery(array $filters, string $message): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($message);

        $this->resolve($filters);
    }

    public function testItRejectsAPageWhoseOffsetOverflows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('is too large.');

        $this->resolve(['page' => (string) PHP_INT_MAX, 'itemsPerPage' => '2']);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function resolve(array $filters): ?Pagination
    {
        return $this->resolver->resolve(new GetCollection(), ['filters' => $filters]);
    }
}
