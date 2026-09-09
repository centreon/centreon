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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Repository\Criteria\PollerCriteria;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerCollectionOutput;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<PollerCollectionOutput>
 */
final readonly class ListPollersProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Poller, PollerCollectionOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: PollerCollectionOutputTransformer::class)]
        private TransformerInterface $transformer,
        private PollerRepository $repository,
        private Pagination $pagination,
        private Security $security,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    /**
     * @return iterable<PollerCollectionOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        $isAdmin = $credentialUser->credential->isAdmin();

        $criteria = new PollerCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array{name?: mixed} $filters */
        $filters = $context['filters'] ?? [];
        $criteria = $this->handleNameFilter($filters['name'] ?? null, $criteria);
        $criteria = $criteria->withExcludeUnknownCentral($this->isCloudPlatform && ! $isAdmin);
        $criteria = $isAdmin ? $criteria : $criteria->withViewerId($credentialUser->credential->userId);

        $pollers = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($pollers as $poller) {
            $resources[] = $this->transformer->transform($poller);
        }

        if (! $pollers instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $pollers->getCurrentPage(),
            $pollers->getItemsPerPage(),
            $pollers->getTotalItems()
        );
    }

    private function handleNameFilter(mixed $nameFilter, PollerCriteria $criteria): PollerCriteria
    {
        if ($nameFilter === null) {
            return $criteria;
        }

        // a client sending "?name=foo" instead of "?name[lk]=foo" lands here as a plain string
        if (! is_array($nameFilter)) {
            throw new BadRequestHttpException('The "name" filter must use the "name[lk]=value" format.');
        }

        $likeValue = $nameFilter['lk'] ?? null;
        if (is_array($likeValue)) {
            $likeValue = reset($likeValue);
        }

        if (! is_string($likeValue) || $likeValue === '') {
            return $criteria;
        }

        return $criteria->withName($likeValue);
    }
}
