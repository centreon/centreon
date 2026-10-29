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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionName;
use App\MonitoringConfiguration\Domain\Model\ListingPageSize;
use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceListingCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceListingRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCollectionOutput;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\ApiPlatform\State\SortAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<ServiceCollectionOutput>
 */
final readonly class ListServicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;
    use SortAwareProviderTrait;

    /** The platform setting that sizes the configuration listings, as legacy pages do. */
    private const PAGE_SIZE_OPTION = 'maxViewConfiguration';

    /**
     * @param TransformerInterface<ServiceListItemView, ServiceCollectionOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: ServiceCollectionOutputTransformer::class)]
        private TransformerInterface $transformer,
        private ServiceListingRepository $repository,
        private HostTemplateRepository $hostTemplateRepository,
        private MediaRepository $mediaRepository,
        private OptionRepository $optionRepository,
        private Pagination $pagination,
        private Security $security,
        // The same name Pagination reads the client's page size from.
        #[Autowire(param: 'api_platform.collection.pagination.items_per_page_parameter_name')]
        private string $itemsPerPageParameter,
    ) {
    }

    /**
     * @return iterable<ServiceCollectionOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];

        $criteria = ServiceListingCriteria::forActor($credentialUser->credential->actingUser());
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $this->itemsPerPage($operation, $context, $filters));
        }
        $criteria = $this->filter($criteria, $filters);

        $items = $this->repository->findAll($criteria);
        $outputs = $this->transformItems(array_values(iterator_to_array($items)));

        if (! $items instanceof Paginator) {
            return $outputs;
        }

        return new TraversablePaginator(
            new \ArrayIterator($outputs),
            $items->getCurrentPage(),
            $items->getItemsPerPage(),
            $items->getTotalItems(),
        );
    }

    /**
     * The client's page size when the request carries one, else the platform setting: the platform's own
     * default does not apply to this listing.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $filters
     */
    private function itemsPerPage(Operation $operation, array $context, array $filters): int
    {
        if (! array_key_exists($this->itemsPerPageParameter, $filters)) {
            return ListingPageSize::fromOptionValue($this->optionRepository->findValueByName(new OptionName(self::PAGE_SIZE_OPTION)))->value;
        }

        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        if ($itemsPerPage <= 0) {
            throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
        }

        return $itemsPerPage;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filter(ServiceListingCriteria $criteria, array $filters): ServiceListingCriteria
    {
        if (($hostName = $this->handleLikeFilter($filters['host_name'] ?? null, 'host_name')) !== null) {
            $criteria = $criteria->withHostName($hostName);
        }
        if (($name = $this->handleLikeFilter($filters['name'] ?? null, 'name')) !== null) {
            $criteria = $criteria->withName($name);
        }
        if (($templateId = $this->handlePositiveIntFilter($filters['template_id'] ?? null, 'template_id')) !== null) {
            $criteria = $criteria->withTemplateId($templateId);
        }
        if (($activated = $this->handleBoolFilter($filters['activated'] ?? null, 'activated')) !== null) {
            $criteria = $criteria->withActivated($activated);
        }
        if (($hostActivated = $this->handleBoolFilter($filters['host_activated'] ?? null, 'host_activated')) !== null) {
            $criteria = $criteria->withHostActivated($hostActivated);
        }

        return $this->handleSort($filters, $criteria);
    }

    /**
     * Resolves the icons of the whole page at once: one lookup of the icons the hosts without their own one
     * inherit from their templates, then one lookup of every media shown, whatever the number of rows.
     *
     * @param list<ServiceListItem> $items
     *
     * @return list<ServiceCollectionOutput>
     */
    private function transformItems(array $items): array
    {
        if ($items === []) {
            return [];
        }

        /** @var array<int, HostId> $hostIdsWithoutIcon */
        $hostIdsWithoutIcon = [];
        foreach ($items as $item) {
            if (! $item->hostIconId instanceof MediaId) {
                $hostIdsWithoutIcon[$item->hostId->value] = $item->hostId;
            }
        }

        /** @var array<int, MediaId> $inheritedHostIconIds indexed by host id */
        $inheritedHostIconIds = $hostIdsWithoutIcon === []
            ? []
            : $this->hostTemplateRepository->findInheritedIconIds(new Collection(array_values($hostIdsWithoutIcon), HostId::class))->toArray();

        /** @var array<int, MediaId> $mediaIds */
        $mediaIds = [];
        foreach ($items as $item) {
            foreach ([$this->hostIconId($item, $inheritedHostIconIds), $item->iconId] as $mediaId) {
                if ($mediaId instanceof MediaId) {
                    $mediaIds[$mediaId->value] = $mediaId;
                }
            }
        }

        /** @var array<int, Media> $media indexed by media id */
        $media = $mediaIds === [] ? [] : $this->mediaRepository->findByIds(new Collection(array_values($mediaIds), MediaId::class))->toArray();

        return array_map(
            fn (ServiceListItem $item): ServiceCollectionOutput => $this->transformer->transform(new ServiceListItemView(
                $item,
                $this->media($this->hostIconId($item, $inheritedHostIconIds), $media),
                $this->media($item->iconId, $media),
            )),
            $items,
        );
    }

    /**
     * The host's own icon, else the one it inherits from its templates. An own icon that no longer resolves
     * gives no icon: it does not fall back on the inherited one, as in the host listing.
     *
     * @param array<int, MediaId> $inheritedHostIconIds
     */
    private function hostIconId(ServiceListItem $item, array $inheritedHostIconIds): ?MediaId
    {
        return $item->hostIconId ?? $inheritedHostIconIds[$item->hostId->value] ?? null;
    }

    /**
     * @param array<int, Media> $media
     */
    private function media(?MediaId $id, array $media): ?Media
    {
        return $id instanceof MediaId ? $media[$id->value] ?? null : null;
    }
}
