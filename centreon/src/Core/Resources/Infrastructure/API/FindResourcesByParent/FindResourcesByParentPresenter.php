<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Infrastructure\API\FindResourcesByParent;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Infrastructure\RealTime\Hypermedia\HypermediaCreator;
use Core\Resources\Application\UseCase\FindResources\Response\ResourceResponseDto;
use Core\Resources\Application\UseCase\FindResourcesByParent\FindResourcesByParentPresenterInterface;
use Core\Resources\Application\UseCase\FindResourcesByParent\FindResourcesByParentResponse;
use Core\Resources\Application\UseCase\FindResourcesByParent\Response\ResourcesByParentResponseDto;
use Core\Resources\Infrastructure\API\ExtraDataNormalizer\ExtraDataNormalizerInterface;

class FindResourcesByParentPresenter extends AbstractPresenter implements FindResourcesByParentPresenterInterface
{
    use HttpUrlTrait;
    use PresenterTrait;
    private const IMAGE_DIRECTORY = '/img/media/',
                  HOST_RESOURCE_TYPE = 'host',
                  SERVICE_RESOURCE_TYPE = 'service';

    /**
     * @param HypermediaCreator $hypermediaCreator
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     * @param \Traversable<ExtraDataNormalizerInterface> $extraDataNormalizers
     */
    public function __construct(
        private readonly HypermediaCreator $hypermediaCreator,
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
        private readonly \Traversable $extraDataNormalizers
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindResourcesByParentResponse|ResponseStatusInterface $response
     */
    public function presentResponse(FindResourcesByParentResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof FindResourcesByParentResponse) {
            $result = [];
            foreach ($response->resources as $resourcesByParent) {
                $parent = $this->createResourceFromResponse($resourcesByParent->parent, $response->extraData);
                $parent['children']['resources'] = $this->createChildrenFromResponse($resourcesByParent->children, $response->extraData);
                $parent['children']['total'] = $resourcesByParent->total;
                $parent['children']['status_count'] = $this->createStatusesCountFromResponse($resourcesByParent);

                $result[] = $parent;
            }

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        } else {
            $this->setResponseStatus($response);
        }
    }

    /**
     * @param ResourcesByParentResponseDto $response
     *
     * @return array<string, int>
     */
    private function createStatusesCountFromResponse(ResourcesByParentResponseDto $response): array
    {
        return [
            'ok' => $response->totalOK,
            'warning' => $response->totalWarning,
            'critical' => $response->totalCritical,
            'unknown' => $response->totalUnknown,
            'pending' => $response->totalPending,
        ];
    }

    /**
     * @param ResourceResponseDto[] $children
     * @param array<string, array<mixed, mixed>> $extraData
     *
     * @return array<int, array<string, mixed>>
     */
    private function createChildrenFromResponse(array $children, array $extraData): array
    {
        return array_map(
            fn (ResourceResponseDto $resource) => $this->createResourceFromResponse($resource, $extraData),
            $children
        );
    }

    /**
     * @param ResourceResponseDto $response
     * @param array<string, array<mixed, mixed>> $extraData
     *
     * @return array<string, array<string, mixed>>
     */
    private function createResourceFromResponse(ResourceResponseDto $response, array $extraData): array
    {
        $duration = $response->lastStatusChange !== null
            ? \CentreonDuration::toString(time() - $response->lastStatusChange->getTimestamp())
            : null;

        $lastCheck = $response->lastCheck !== null
            ? \CentreonDuration::toString(time() - $response->lastCheck->getTimestamp())
            : null;

        $severity = $response->severity !== null
            ? [
                'id' => $response->severity->id,
                'name' => $response->severity->name,
                'level' => $response->severity->level,
                'type' => $response->severity->type,
                'icon' => [
                    'id' => $response->severity->icon->id,
                    'name' => $response->severity->icon->name,
                    'url' => $this->generateNormalizedIconUrl($response->severity->icon->url),
                ],
            ]
            : null;

        $icon = $response->icon !== null
            ? [
                'id' => $response->icon->id,
                'name' => $response->icon->name,
                'url' => $this->generateNormalizedIconUrl($response->icon->url),
            ]
            : null;

        $parameters = [
            'type' => $response->type,
            'serviceId' => $response->serviceId,
            'hostId' => $response->hostId,
            'hasGraphData' => $response->hasGraphData,
            'internalId' => $response->internalId,
        ];

        $endpoints = $this->hypermediaCreator->createEndpoints($parameters);

        $links = [
            'endpoints' => [
                'details' => $endpoints['details'],
                'timeline' => $endpoints['timeline'],
                'status_graph' => $endpoints['status_graph'] ?? null,
                'performance_graph' => $endpoints['performance_graph'] ?? null,
                'acknowledgement' => $endpoints['acknowledgement'],
                'downtime' => $endpoints['downtime'],
                'check' => $endpoints['check'],
                'forced_check' => $endpoints['forced_check'],
                'metrics' => $endpoints['metrics'],
            ],
            'uris' => $this->hypermediaCreator->createInternalUris($parameters),
            'externals' => [
                'action_url' => $response->actionUrl !== null
                    ? $this->generateUrlWithMacrosResolved($response->actionUrl, $response)
                    : $response->actionUrl,
                'notes' => [
                    'label' => $response->notes?->label,
                    'url' => $response->notes?->url !== null
                        ? $this->generateUrlWithMacrosResolved($response->notes->url, $response)
                        : $response->notes?->url,
                ],
            ],
        ];

        $resource = [
            'uuid' => $response->uuid,
            'duration' => $duration,
            'last_check' => $lastCheck,
            'short_type' => $response->shortType,
            'id' => $response->id,
            'type' => $response->type,
            'alias' => $response->alias,
            'fqdn' => $response->fqdn,
            'icon' => $icon,
            'monitoring_server_name' => $response->monitoringServerName,
            'status' => [
                'code' => $response->status?->code,
                'name' => $response->status?->name,
                'severity_code' => $response->status?->severityCode,
            ],
            'is_in_downtime' => $response->isInDowntime,
            'is_acknowledged' => $response->isAcknowledged,
            'has_active_checks_enabled' => $response->withActiveChecks,
            'has_passive_checks_enabled' => $response->withPassiveChecks,
            'last_status_change' => $this->formatDateToIso8601($response->lastStatusChange),
            'tries' => $response->tries,
            'information' => $response->information,
            'performance_data' => null,
            'is_notification_enabled' => false,
            'severity' => $severity,
            'links' => $links,
        ];

        if (
            $response->type === self::HOST_RESOURCE_TYPE
            && $response->parent !== null
        ) {
            $resource['name'] = $response->name;
            $resource['parent'] = null;
            $resource['children'] = [];
            $resource['extra'] = $response->parent->resourceId !== null
                ? $this->normalizeExtraDataForResource($response->parent->resourceId, $extraData)
                : [];
        } else {
            $resource['resource_name'] = $response->name;
            $resource['parent'] = ['id' => $response->hostId];
            $resource['parent']['extra'] = $response->resourceId !== null
                ? $this->normalizeExtraDataForResource($response->resourceId, $extraData)
                : [];
        }

        return $resource;
    }

    /**
     * @param int $resourceId
     * @param array<string, array<mixed, mixed>> $extraData
     *
     * @return mixed[]
     */
    private function normalizeExtraDataForResource(int $resourceId, array $extraData): array
    {
        $data = [];
        foreach ($extraData as $sourceName => $sourceData) {
            foreach (iterator_to_array($this->extraDataNormalizers) as $provider) {
                if (!$provider->isValidFor($sourceName)) {
                    continue;
                }
                if (!array_key_exists($resourceId, $sourceData)) {
                    continue;
                }
                $data[$sourceName] = $provider->normalizeExtraDataForResource(
                    $sourceData[$resourceId],
                );
            }
        }

        return $data;
    }

    /**
     * @param string $url
     * @param ResourceResponseDto $resource
     *
     * @return string
     */
    private function generateUrlWithMacrosResolved(string $url, ResourceResponseDto $resource): string
    {
        $isServiceTypedResource = $resource->type === self::SERVICE_RESOURCE_TYPE;

        $macrosConcordanceArray = [
            '$HOSTADDRESS$' => $isServiceTypedResource ? $resource->parent?->fqdn : $resource->fqdn,
            '$HOSTNAME$' => $isServiceTypedResource ? $resource->parent?->name : $resource->name,
            '$HOSTSTATE$' => $isServiceTypedResource ? $resource->parent?->status?->name : $resource->status?->name,
            '$HOSTSTATEID$' => $isServiceTypedResource ? (string) $resource->parent?->status?->code : (string) $resource->status?->code,
            '$HOSTALIAS$' => $isServiceTypedResource ? $resource->parent?->alias : $resource->alias,
            '$SERVICEDESC$' => $isServiceTypedResource ? $resource->name : '',
            '$SERVICESTATE$' => $isServiceTypedResource ? $resource->status?->name : '',
            '$SERVICESTATEID$' => $isServiceTypedResource ? (string) $resource->status?->code : '',
        ];

        return str_replace(array_keys($macrosConcordanceArray), array_values($macrosConcordanceArray), $url);
    }

    /**
     * @param string|null $url
     *
     * @return string|null
     */
    private function generateNormalizedIconUrl(?string $url): ?string
    {
        return $url !== null
            ? $this->getBaseUri() . self::IMAGE_DIRECTORY . $url
            : $url;
    }
}
