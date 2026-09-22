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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\TimePeriod;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriod;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TimePeriodCriteria;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodChoicesOutput;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<TimePeriodChoicesOutput>
 */
final readonly class ListTimePeriodsChoicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<TimePeriod, TimePeriodChoicesOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: TimePeriodChoicesTransformer::class)]
        private TransformerInterface $transformer,
        private TimePeriodRepository $repository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<TimePeriodChoicesOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // No viewer scoping: time periods carry no acl_resources relation, so every user passing
        // the permission gate sees them all, exactly as legacy FindTimePeriods does.
        $criteria = new TimePeriodCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage === 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array{name?: mixed} $filters */
        $filters = $context['filters'] ?? [];
        foreach ($this->handleOperatorFilter($filters['name'] ?? null, 'name', TimePeriodCriteria::ALLOWED_OPERATORS) as $operator => $values) {
            foreach ($values as $value) {
                $criteria = $criteria->withName($value, $operator);
            }
        }

        $timePeriods = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($timePeriods as $timePeriod) {
            $resources[] = $this->transformer->transform($timePeriod);
        }

        if (! $timePeriods instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $timePeriods->getCurrentPage(),
            $timePeriods->getItemsPerPage(),
            $timePeriods->getTotalItems()
        );
    }
}
