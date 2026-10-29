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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
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

    /**
     * @param TransformerInterface<Service, ServiceCollectionOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: ServiceCollectionOutputTransformer::class)]
        private TransformerInterface $transformer,
        private ServiceRepository $repository,
        private HostRepository $hostRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private MediaRepository $mediaRepository,
        private MediaUrlGenerator $mediaUrlGenerator,
        private Pagination $pagination,
        private Security $security,
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

        $criteria = ServiceCriteria::forActor($credentialUser->credential->actingUser());
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }
        $criteria = $this->filter($criteria, $filters);

        $services = $this->repository->findAll($criteria);
        $outputs = $this->transformServices(array_values(iterator_to_array($services)));

        if (! $services instanceof Paginator) {
            return $outputs;
        }

        return new TraversablePaginator(
            new \ArrayIterator($outputs),
            $services->getCurrentPage(),
            $services->getItemsPerPage(),
            $services->getTotalItems(),
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filter(ServiceCriteria $criteria, array $filters): ServiceCriteria
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
     * Sets what a listed service shows besides its own values, looked up once for the whole page whatever the
     * number of rows, as the host listing does: its host's name and icon (own, else inherited from the host
     * templates), its icon (own, else inherited from its template chain), each check interval (own, else
     * inherited), its template's name, and every media shown.
     *
     * @param list<Service> $services
     *
     * @return list<ServiceCollectionOutput>
     */
    private function transformServices(array $services): array
    {
        if ($services === []) {
            return [];
        }

        /** @var array<int, HostId> $hostIds */
        $hostIds = [];
        /** @var array<int, ServiceId> $serviceIdsWithoutIcon */
        $serviceIdsWithoutIcon = [];
        /** @var array<int, ServiceId> $serviceIdsMissingAnInterval */
        $serviceIdsMissingAnInterval = [];
        /** @var array<int, ServiceTemplateId> $templateIds */
        $templateIds = [];
        foreach ($services as $service) {
            $hostIds[$service->hostId->value] = $service->hostId;
            if (! $service->iconId instanceof MediaId) {
                $serviceIdsWithoutIcon[$service->id()->value] = $service->id();
            }
            if ($service->checkIntervals->normal === null || $service->checkIntervals->retry === null) {
                $serviceIdsMissingAnInterval[$service->id()->value] = $service->id();
            }
            if ($service->templateId instanceof ServiceTemplateId) {
                $templateIds[$service->templateId->value] = $service->templateId;
            }
        }

        $hostIdCollection = new Collection(array_values($hostIds), HostId::class);
        /** @var array<int, HostName> $hostNames */
        $hostNames = $this->hostRepository->findNamesByIds($hostIdCollection)->toArray();
        /** @var array<int, MediaId> $hostIconIds own icons, indexed by host id */
        $hostIconIds = $this->hostRepository->findIconIdsByIds($hostIdCollection)->toArray();
        $hostIdsWithoutIcon = array_values(array_diff_key($hostIds, $hostIconIds));
        /** @var array<int, MediaId> $inheritedHostIconIds indexed by host id */
        $inheritedHostIconIds = $hostIdsWithoutIcon === []
            ? []
            : $this->hostTemplateRepository->findInheritedIconIds(new Collection($hostIdsWithoutIcon, HostId::class))->toArray();

        /** @var array<int, MediaId> $inheritedIconIds indexed by service id */
        $inheritedIconIds = $serviceIdsWithoutIcon === []
            ? []
            : $this->serviceTemplateRepository->findInheritedIconIds(new Collection(array_values($serviceIdsWithoutIcon), ServiceId::class))->toArray();
        /** @var array<int, ServiceCheckIntervals> $inheritedIntervals indexed by service id */
        $inheritedIntervals = $serviceIdsMissingAnInterval === []
            ? []
            : $this->serviceTemplateRepository->findInheritedCheckIntervals(new Collection(array_values($serviceIdsMissingAnInterval), ServiceId::class))->toArray();
        /** @var array<int, ServiceTemplateName> $templateNames */
        $templateNames = $templateIds === []
            ? []
            : $this->serviceTemplateRepository->findNamesByIds(new Collection(array_values($templateIds), ServiceTemplateId::class))->toArray();

        /** @var array<int, MediaId> $mediaIds */
        $mediaIds = [];
        foreach ([...array_values($hostIconIds), ...array_values($inheritedHostIconIds), ...array_values($inheritedIconIds)] as $mediaId) {
            $mediaIds[$mediaId->value] = $mediaId;
        }
        foreach ($services as $service) {
            if ($service->iconId instanceof MediaId) {
                $mediaIds[$service->iconId->value] = $service->iconId;
            }
        }
        /** @var array<int, Media> $media indexed by media id */
        $media = $mediaIds === [] ? [] : $this->mediaRepository->findByIds(new Collection(array_values($mediaIds), MediaId::class))->toArray();

        $outputs = [];
        foreach ($services as $service) {
            $hostId = $service->hostId->value;
            $output = $this->transformer->transform($service);

            $host = ['id' => $hostId, 'name' => $hostNames[$hostId]->value ?? ''];
            // An own icon that no longer resolves gives no icon: it does not fall back on the inherited one.
            $hostIcon = $this->icon($hostIconIds[$hostId] ?? $inheritedHostIconIds[$hostId] ?? null, $media);
            if ($hostIcon !== null) {
                $host['icon'] = $hostIcon;
            }
            $output->host = $host;

            $output->icon = $this->icon($service->iconId ?? $inheritedIconIds[$service->id()->value] ?? null, $media);

            $inherited = $inheritedIntervals[$service->id()->value] ?? null;
            $output->normalCheckInterval = $service->checkIntervals->normal ?? $inherited?->normal;
            $output->retryCheckInterval = $service->checkIntervals->retry ?? $inherited?->retry;

            // A template that no longer exists (no foreign key on the reference) shows none, as in the host listing.
            if ($service->templateId instanceof ServiceTemplateId && isset($templateNames[$service->templateId->value])) {
                $output->template = ['id' => $service->templateId->value, 'name' => $templateNames[$service->templateId->value]->value];
            }

            $outputs[] = $output;
        }

        return $outputs;
    }

    /**
     * @param array<int, Media> $media
     *
     * @return array{id: int, name: string, url: string}|null
     */
    private function icon(?MediaId $id, array $media): ?array
    {
        $icon = $id instanceof MediaId ? $media[$id->value] ?? null : null;

        return $icon instanceof Media
            ? ['id' => $icon->id()->value, 'name' => $icon->name->value, 'url' => $this->mediaUrlGenerator->generate($icon)]
            : null;
    }
}
