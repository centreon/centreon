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

namespace App\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination as ApiPlatformPagination;
use App\Shared\Domain\Aggregate\Option\OptionName;
use App\Shared\Domain\Exception\OptionDoesNotExistException;
use App\Shared\Domain\Repository\OptionRepository;
use App\Shared\Domain\Repository\Pagination;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Turns the pagination query parameters of a collection into a domain {@see Pagination}.
 *
 * The raw parameters are validated here rather than through API Platform's own Pagination, which
 * casts them to int and throws an exception mapped to HTTP 500. A page size above the maximum is
 * capped, and a missing one defaults to the platform's "Limit per page" setting.
 */
final readonly class PaginationResolver
{
    private const DEFAULT_ITEMS_PER_PAGE_OPTION = 'maxViewConfiguration';

    public function __construct(
        private ApiPlatformPagination $pagination,
        private OptionRepository $optionRepository,
        #[Autowire(param: 'api_platform.collection.pagination.items_per_page')]
        private int $fallbackItemsPerPage,
        #[Autowire(param: 'api_platform.collection.pagination.page_parameter_name')]
        private string $pageParameterName = 'page',
        #[Autowire(param: 'api_platform.collection.pagination.items_per_page_parameter_name')]
        private string $itemsPerPageParameterName = 'itemsPerPage',
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws BadRequestHttpException
     */
    public function resolve(Operation $operation, array $context): ?Pagination
    {
        if (! $this->pagination->isEnabled($operation, $context)) {
            return null;
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $page = $this->parsePositiveInt($filters[$this->pageParameterName] ?? null, $this->pageParameterName) ?? 1;
        $itemsPerPage = $this->parsePositiveInt($filters[$this->itemsPerPageParameterName] ?? null, $this->itemsPerPageParameterName)
            ?? $this->defaultItemsPerPage();

        try {
            return new Pagination($page, min($itemsPerPage, Pagination::MAX_ITEMS_PER_PAGE));
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }

    private function parsePositiveInt(mixed $value, string $parameterName): ?int
    {
        if ($value === null) {
            return null;
        }

        $parsed = filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new BadRequestHttpException(sprintf('The "%s" parameter must be a positive integer.', $parameterName));
        }

        return $parsed;
    }

    /**
     * The setting is only checked as numeric by its form, so an invalid value falls back to
     * API Platform's default instead of failing every collection.
     */
    private function defaultItemsPerPage(): int
    {
        try {
            $setting = $this->optionRepository->getByName(new OptionName(self::DEFAULT_ITEMS_PER_PAGE_OPTION))->value->value;
        } catch (OptionDoesNotExistException) {
            return $this->fallbackItemsPerPage;
        }

        $itemsPerPage = filter_var($setting, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $itemsPerPage === false ? $this->fallbackItemsPerPage : $itemsPerPage;
    }
}
