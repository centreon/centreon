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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateNameResolver;
use App\MonitoringConfiguration\Domain\Repository\PollerNameResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\Security\Domain\AdminResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<HostCollectionOutput>
 */
final readonly class ListHostsProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<HostListView, HostCollectionOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: HostCollectionOutputTransformer::class)]
        private TransformerInterface $transformer,
        private HostRepository $repository,
        private PollerNameResolver $pollerNameResolver,
        private HostTemplateNameResolver $hostTemplateNameResolver,
        private AdminResolver $adminResolver,
        private Pagination $pagination,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<HostCollectionOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        $isAdmin = $this->adminResolver->resolve($credentialUser->credential);

        $criteria = new HostCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        if (($name = $this->handleLikeFilter($filters['name'] ?? null, 'name')) !== null) {
            $criteria = $criteria->withName($name);
        }

        if (($templateId = $this->handlePositiveIntFilter($filters['template_id'] ?? null, 'template_id')) !== null) {
            $criteria = $criteria->withTemplateId($templateId);
        }
        if (($groupId = $this->handlePositiveIntFilter($filters['group_id'] ?? null, 'group_id')) !== null) {
            $criteria = $criteria->withGroupId($groupId);
        }
        if (($pollerId = $this->handlePositiveIntFilter($filters['poller_id'] ?? null, 'poller_id')) !== null) {
            $criteria = $criteria->withPollerId($pollerId);
        }
        if (($activated = $this->handleBoolFilter($filters['activated'] ?? null, 'activated')) !== null) {
            $criteria = $criteria->withActivated($activated);
        }

        $criteria = $isAdmin ? $criteria : $criteria->withViewerId($credentialUser->credential->userId);

        $hosts = $this->repository->findAll($criteria);
        $hostList = array_values(iterator_to_array($hosts));

        $resources = $this->transformHosts($hostList);

        if (! $hosts instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $hosts->getCurrentPage(),
            $hosts->getItemsPerPage(),
            $hosts->getTotalItems()
        );
    }

    /**
     * @param list<Host> $hostList
     *
     * @return list<HostCollectionOutput>
     */
    private function transformHosts(array $hostList): array
    {
        /** @var array<int, PollerId> $pollerIds */
        $pollerIds = [];
        /** @var array<int, HostTemplateId> $templateIds */
        $templateIds = [];
        foreach ($hostList as $host) {
            $pollerIds[$host->pollerId->value] = $host->pollerId;
            foreach ($host->templateIds as $templateId) {
                $templateIds[$templateId->value] = $templateId;
            }
        }

        $pollerNames = $this->pollerNameResolver->resolveNames(new Collection(array_values($pollerIds), PollerId::class));
        $templateNames = $this->hostTemplateNameResolver->resolveNames(
            new Collection(array_values($templateIds), HostTemplateId::class)
        );

        $resources = [];
        foreach ($hostList as $host) {
            $templates = [];
            foreach ($host->templateIds as $templateId) {
                if (isset($templateNames[$templateId->value])) {
                    $templates[] = new HostTemplateOutput($templateId->value, $templateNames[$templateId->value]);
                }
            }

            $view = new HostListView(
                host: $host,
                poller: new HostPollerOutput($host->pollerId->value, $pollerNames[$host->pollerId->value] ?? ''),
                templates: $templates,
            );

            $resources[] = $this->transformer->transform($view);
        }

        return $resources;
    }
}
