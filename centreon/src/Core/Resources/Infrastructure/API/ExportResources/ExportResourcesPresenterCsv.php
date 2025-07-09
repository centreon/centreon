<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Resources\Infrastructure\API\ExportResources;

use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Domain\Collection\StringCollection;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Infrastructure\Common\Presenter\CsvFormatter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesPresenterInterface;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesResponse;

/**
 * Class
 *
 * @class ExportResourcesPresenterCsv
 * @package Core\Resources\Infrastructure\API\ExportResources
 */
final class ExportResourcesPresenterCsv extends AbstractPresenter implements ExportResourcesPresenterInterface
{
    use HttpUrlTrait;

    /** @var ExportResourcesViewModel */
    private ExportResourcesViewModel $viewModel;

    /**
     * ExportResourcesPresenterCsv constructor
     *
     * @param CsvFormatter $presenterFormatter
     * @param ExceptionLogger $exceptionLogger
     */
    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
        private readonly ExceptionLogger $exceptionLogger
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param ExportResourcesResponse|ResponseStatusInterface $response
     *
     * @return void
     */
    public function presentResponse(ExportResourcesResponse|ResponseStatusInterface $response): void
    {
        $this->viewModel = new ExportResourcesViewModel();

        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                $this->exceptionLogger->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }

        try {
            $csvHeader = $this->getHeaderByFilteredColumns($response->getFilteredColumns());
        } catch (CollectionException $exception) {
            $this->exceptionLogger->log($exception);
            $this->setResponseStatus(new ErrorResponse('An error occurred while filtering columns'));

            return;
        }

        if (! $response->getFilteredColumns()->isEmpty()) {
            $csvHeader = $this->sortHeaderByFilteredColumns($csvHeader, $response->getFilteredColumns());
        }

        $csvResources = $this->transformToCsvByHeader($response->getResources(), $csvHeader);

        $this->viewModel->setHeaders($csvHeader);

        $this->viewModel->setResources($csvResources);
        $this->viewModel->setExportedFormat($response->getExportedFormat());
    }

    /**
     * @return ExportResourcesViewModel
     */
    public function getViewModel(): ExportResourcesViewModel
    {
        return $this->viewModel;
    }

    // ---------------------------------- PRIVATE METHODS ---------------------------------- //

    /**
     * @param \Traversable<ResourceEntity> $resources
     * @param StringCollection $csvHeader
     *
     * @return \Traversable<array<string,mixed>>
     */
    private function transformToCsvByHeader(\Traversable $resources, StringCollection $csvHeader): \Traversable
    {
        /** @var ResourceEntity $resource */
        foreach ($resources as $resource) {
            $resource = [
                _('Resource Type') => _($this->formatLabel($resource->getType() ?? '')),
                _('Resource Name') => _($resource->getName() ?? ''),
                _('Status') => _($this->formatLabel($resource->getStatus()?->getName() ?? '')),
                _('Parent Resource Type') => _($this->formatLabel($resource->getParent()?->getType() ?? '')),
                _('Parent Resource Name') => _($resource->getParent()?->getName() ?? ''),
                _('Parent Resource Status') => _(
                    $this->formatLabel($resource->getParent()?->getStatus()?->getName() ?? '')
                ),
                _('Duration') => $resource->getDuration() ?? '',
                _('Last Check') => $resource->getLastCheckAsString() ?? '',
                _('Information') => $resource->getInformation() ?? '',
                _('Tries') => $resource->getTries() ?? '',
                _('Severity') => $resource->getSeverity()?->getLevel() ?? '',
                _('Notes') => $this->getResourceNotes($resource),
                _('Action') => $this->formatUrl(
                    $resource->getLinks()->getExternals()->getActionUrl() ?? '', $resource
                ),
                _('State') => _($this->getResourceState($resource)),
                _('Alias') => $resource->getAlias() ?? '',
                _('Parent alias') => $resource->getParent()?->getAlias() ?? '',
                _('FQDN / Address') => $resource->getFqdn() ?? '',
                _('Monitoring server') => $resource->getMonitoringServerName(),
                _('Notif') => $resource->isNotificationEnabled()
                    ? _('Notifications enabled') : _('Notifications disabled'),
                _('Check') => _($this->getResourceCheck($resource)),
            ];

            $line = array_map(
                fn($key) => $resource[$key],
                $csvHeader->values()
            );

            yield $line;
        }
    }

    /**
     * @param StringCollection $filteredColumns
     *
     * @throws CollectionException
     * @return StringCollection
     */
    private function getHeaderByFilteredColumns(StringCollection $filteredColumns): StringCollection
    {
        $csvHeader = StringCollection::create([
            'resource_type' => _('Resource Type'),
            'resource_name' => _('Resource Name'),
            'status' => _('Status'),
            'parent_resource_type' => _('Parent Resource Type'),
            'parent_resource_name' => _('Parent Resource Name'),
            'parent_resource_status' => _('Parent Resource Status'),
            'duration' => _('Duration'),
            'last_check' => _('Last Check'),
            'information' => _('Information'),
            'tries' => _('Tries'),
            'severity' => _('Severity'),
            'notes_url' => _('Notes'),
            'action_url' => _('Action'),
            'state' => _('State'),
            'alias' => _('Alias'),
            'parent_alias' => _('Parent alias'),
            'fqdn' => _('FQDN / Address'),
            'monitoring_server_name' => _('Monitoring server'),
            'notification' => _('Notif'),
            'checks' => _('Check'),
        ]);

        if ($filteredColumns->isEmpty()) {
            return $csvHeader;
        }

        return $csvHeader->filterOnKey(function ($key) use ($filteredColumns) {
            // if the key is a resource or parent_resource, we keep all columns starting with this key
            if (str_starts_with($key, 'resource_')) {
                $key = 'resource';
            } else {
                if (str_starts_with($key, 'parent_resource_')) {
                    $key = 'parent_resource';
                }
            }

            return $filteredColumns->contains($key);
        });
    }

    /**
     * @param StringCollection $csvHeader
     * @param StringCollection $filteredColumns
     *
     * @return StringCollection
     */
    private function sortHeaderByFilteredColumns(
        StringCollection $csvHeader,
        StringCollection $filteredColumns
    ): StringCollection {
        $csvHeader->sortByKeys(function ($keyA, $keyB) use ($filteredColumns) {
            // if the key is a resource or parent_resource, we keep all columns starting with this key
            if (str_starts_with($keyA, 'resource_')) {
                $keyA = 'resource';
            } else {
                if (str_starts_with($keyA, 'parent_resource_')) {
                    $keyA = 'parent_resource';
                }
            }
            if (str_starts_with($keyB, 'resource_')) {
                $keyB = 'resource';
            } else {
                if (str_starts_with($keyB, 'parent_resource_')) {
                    $keyB = 'parent_resource';
                }
            }

            return $filteredColumns->indexOf($keyA) <=> $filteredColumns->indexOf($keyB);
        });

        return $csvHeader;
    }

    /**
     * @param string $label
     *
     * @return string
     */
    private function formatLabel(string $label): string
    {
        return ucfirst(mb_strtolower($label));
    }

    /**
     * @param ResourceEntity $resource
     *
     * @return string
     */
    private function getResourceNotes(ResourceEntity $resource): string
    {
        $notes = '';

        if (! is_null($resource->getLinks()->getExternals()->getNotes())) {
            $notes = $resource->getLinks()->getExternals()->getNotes()->getUrl() ?? null;
            $notes = $notes ? $this->formatUrl($notes, $resource) : null;
            $notes = $notes ?: $resource->getLinks()->getExternals()->getNotes()->getLabel() ?? '';
        }

        return $notes;
    }

    /**
     * @param ResourceEntity $resource
     *
     * @return string
     */
    private function getResourceState(ResourceEntity $resource): string
    {
        $state = [];

        if ($resource->getAcknowledged()) {
            $state[] = 'Acknowledged';
        }
        if ($resource->getInDowntime()) {
            $state[] = 'In Downtime';
        }
        if ($resource->isInFlapping()) {
            $state[] = 'Flapping';
        }

        return implode(', ', $state);
    }

    /**
     * @param ResourceEntity $resource
     *
     * @return string
     */
    private function getResourceCheck(ResourceEntity $resource): string
    {
        $check = '';

        if (! $resource->getActiveChecks() && ! $resource->getPassiveChecks()) {
            $check = 'All checks are disabled';
        } elseif ($resource->getActiveChecks() && $resource->getPassiveChecks()) {
            $check = 'All checks are enabled';
        } elseif ($resource->getActiveChecks()) {
            $check = 'Only active checks are enabled';
        } elseif ($resource->getPassiveChecks()) {
            $check = 'Only passive checks are enabled';
        }

        return $check;
    }

    /**
     * @param string $url
     * @param ResourceEntity $resource
     *
     * @return string
     */
    private function formatUrl(string $url, ResourceEntity $resource): string
    {
        if (empty($url)) {
            return '';
        }

        $url = $this->replaceMacrosInUrl($url, $resource);

        if (! str_starts_with($url, 'http')) {
            $baseurl = $this->getHost(true);

            if (! str_starts_with($url, '/')) {
                $url = '/' . $url;
            }

            if (str_starts_with($url, '/centreon/')) {
                $url = str_replace('/centreon/', '/', $url);
            }

            $url = $baseurl . $url;
        }

        return $url;
    }

    /**
     * @param string $url
     * @param ResourceEntity $resource
     *
     * @return string
     */
    private function replaceMacrosInUrl(string $url, ResourceEntity $resource): string
    {
        $isServiceTypedResource = $resource->getType() === 'service';

        $macrosConcordanceArray = [
            '$HOSTADDRESS$' => $isServiceTypedResource ? $resource->getParent()?->getFqdn() : $resource->getFqdn(),
            '$HOSTNAME$' => $isServiceTypedResource ? $resource->getParent()?->getName() : $resource->getName(),
            '$HOSTSTATE$' => $isServiceTypedResource ? $resource->getParent()?->getStatus()?->getName(
            ) : $resource->getStatus()?->getName(),
            '$HOSTSTATEID$' => $isServiceTypedResource ? (string) $resource->getParent()?->getStatus()?->getCode(
            ) : (string) $resource->getStatus()?->getCode(),
            '$HOSTALIAS$' => $isServiceTypedResource ? $resource->getParent()?->getAlias() : $resource->getAlias(),
            '$SERVICEDESC$' => $isServiceTypedResource ? $resource->getName() : '',
            '$SERVICESTATE$' => $isServiceTypedResource ? $resource->getStatus()?->getName() : '',
            '$SERVICESTATEID$' => $isServiceTypedResource ? (string) $resource->getStatus()?->getCode() : '',
        ];

        return str_replace(array_keys($macrosConcordanceArray), array_values($macrosConcordanceArray), $url);
    }
}
