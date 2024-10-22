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

namespace Core\Resources\Infrastructure\API\FindResources;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Infrastructure\RealTime\Hypermedia\HypermediaCreator;
use Core\Resources\Application\UseCase\FindResources\FindResourcesPresenterInterface;
use Core\Resources\Application\UseCase\FindResources\FindResourcesResponse;
use Core\Resources\Application\UseCase\FindResources\Response\ResourceResponseDto;
use Core\Resources\Infrastructure\API\ExtraDataNormalizer\ExtraDataNormalizerInterface;

class FindResourcesPresenter extends AbstractPresenter implements FindResourcesPresenterInterface
{
    use HttpUrlTrait, PresenterTrait;
    private const IMAGE_DIRECTORY = '/img/media/';
    private const SERVICE_RESOURCE_TYPE = 'service';

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

    public function presentResponse(FindResourcesResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof FindResourcesResponse) {
            $result = [];

            foreach ($response->resources as $resource) {
                $parentResource = ($resource->parent !== null && $resource->parent->resourceId !== null)
                    ? [
                        'uuid' => $resource->parent->uuid,
                        'id' => $resource->parent->id,
                        'name' => $resource->parent->name,
                        'type' => $resource->parent->type,
                        'short_type' => $resource->parent->shortType,
                        'status' => [
                            'code' => $resource->parent->status?->code,
                            'name' => $resource->parent->status?->name,
                            'severity_code' => $resource->parent->status?->severityCode,
                        ],
                        'alias' => $resource->parent->alias,
                        'fqdn' => $resource->parent->fqdn,
                        'monitoring_server_name' => $resource->parent->monitoringServerName,
                        'extra' => $this->normalizeExtraDataForResource(
                            $resource->parent->resourceId,
                            $response->extraData,
                        ),
                    ]
                    : null;

                $duration = $resource->lastStatusChange !== null
                    ? \CentreonDuration::toString(time() - $resource->lastStatusChange->getTimestamp())
                    : null;

                $lastCheck = $resource->lastCheck !== null
                    ? \CentreonDuration::toString(time() - $resource->lastCheck->getTimestamp())
                    : null;

                $severity = $resource->severity !== null
                    ? [
                        'id' => $resource->severity->id,
                        'name' => $resource->severity->name,
                        'level' => $resource->severity->level,
                        'type' => $resource->severity->type,
                        'icon' => [
                            'id' => $resource->severity->icon->id,
                            'name' => $resource->severity->icon->name,
                            'url' => $this->generateNormalizedIconUrl($resource->severity->icon->url),
                        ],
                    ]
                    : null;

                $icon = $resource->icon !== null
                    ? [
                        'id' => $resource->icon->id,
                        'name' => $resource->icon->name,
                        'url' => $this->generateNormalizedIconUrl($resource->icon->url),
                    ]
                    : null;

                $parameters = [
                    'type' => $resource->type,
                    'serviceId' => $resource->serviceId,
                    'hostId' => $resource->hostId,
                    'hasGraphData' => $resource->hasGraphData,
                    'internalId' => $resource->internalId,
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
                        'metrics' => $endpoints['metrics'] ?? null,
                    ],
                    'uris' => $this->hypermediaCreator->createInternalUris($parameters),
                    'externals' => [
                        'action_url' => $resource->actionUrl !== null
                            ? $this->generateUrlWithMacrosResolved($resource->actionUrl, $resource)
                            : $resource->actionUrl,
                        'notes' => [
                            'label' => $resource->notes?->label,
                            'url' => $resource->notes?->url !== null
                                ? $this->generateUrlWithMacrosResolved($resource->notes->url, $resource)
                                : $resource->notes?->url,
                        ],
                    ],
                ];

                $result[] = [
                    'uuid' => $resource->uuid,
                    'duration' => $duration,
                    'last_check' => $lastCheck,
                    'short_type' => $resource->shortType,
                    'id' => $resource->id,
                    'type' => $resource->type,
                    'name' => $resource->name,
                    'alias' => $resource->alias,
                    'fqdn' => $resource->fqdn,
                    'host_id' => $resource->hostId,
                    'service_id' => $resource->serviceId,
                    'icon' => $icon,
                    'monitoring_server_name' => $resource->monitoringServerName,
                    'parent' => $parentResource,
                    'status' => [
                        'code' => $resource->status?->code,
                        'name' => $resource->status?->name,
                        'severity_code' => $resource->status?->severityCode,
                    ],
                    'is_in_downtime' => $resource->isInDowntime,
                    'is_acknowledged' => $resource->isAcknowledged,
                    'has_active_checks_enabled' => $resource->withActiveChecks,
                    'has_passive_checks_enabled' => $resource->withPassiveChecks,
                    'last_status_change' => $this->formatDateToIso8601($resource->lastStatusChange),
                    'tries' => $resource->tries,
                    'information' => $resource->information,
                    'performance_data' => null,
                    'is_notification_enabled' => false,
                    'severity' => $severity,
                    'links' => $links,
                    'extra' => $resource->resourceId !== null
                        ? $this->normalizeExtraDataForResource($resource->resourceId, $response->extraData)
                        : [],
                ];
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
                if ($provider->isValidFor($sourceName)) {
                    if (array_key_exists($resourceId, $sourceData)) {
                        $data[$sourceName] = $provider->normalizeExtraDataForResource(
                            $sourceData[$resourceId],
                        );
                    }
                }
            }
        }

        return $data;
    }

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
