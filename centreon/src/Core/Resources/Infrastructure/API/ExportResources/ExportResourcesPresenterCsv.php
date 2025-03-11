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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionHandler;
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
class ExportResourcesPresenterCsv extends AbstractPresenter implements ExportResourcesPresenterInterface
{
    /** @var ExportResourcesViewModel */
    private ExportResourcesViewModel $viewModel;

    /**
     * ExportResourcesPresenterCsv constructor
     *
     * @param CsvFormatter $presenterFormatter
     */
    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
        private readonly ContactInterface $contact,
        private readonly ExceptionHandler $exceptionHandler,
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

        $this->viewModel->setExportedFormat($response->getExportedFormat());

        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                $this->exceptionHandler->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }
        $csvResources = $this->transformToCsv($response->getResources());
        if ($response->getFilteredColumns() !== []) {
            $csvHeader = $this->setHeaderByFilteredColumns($response->getFilteredColumns());
            $csvResources = $this->filterColumns($csvResources, $csvHeader);
        }
        $this->viewModel->setResources($csvResources);
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
     *
     * @return \Traversable<array<string,mixed>>
     */
    private function transformToCsv(\Traversable $resources): \Traversable
    {
        /** @var ResourceEntity $resource */
        foreach ($resources as $resource) {
            yield [
                _('Resource Type') => _($this->formatLabel($resource->getType() ?? '')),
                _('Resource Name') => _($resource->getName() ?? ''),
                _('Status') => _($this->formatLabel($resource->getStatus()?->getName() ?? '')),
                _('Parent Resource Type') => _($this->formatLabel($resource->getParent()?->getType() ?? '')),
                _('Parent Resource Name') => _($resource->getParent()?->getName() ?? ''),
                _('Parent Resource Status') => _(
                    $this->formatLabel($resource->getParent()?->getStatus()?->getName() ?? '')
                ),
                _('Duration') => $resource->getDuration() ?? '',
                _('Last Check') => $this->formatDate($resource->getLastCheck()),
                _('Information') => $resource->getInformation() ?? '',
                _('Tries') => $resource->getTries() ?? '',
                _('Severity') => $resource->getSeverity()?->getLevel() ?? '',
                _('Notes') => $this->getResourceNotes($resource),
                _('Action') => $this->formatUrl($resource->getLinks()->getExternals()->getActionUrl() ?? ''),
                _('State') => _($this->getResourceState($resource)),
                _('Alias') => $resource->getAlias() ?? '',
                _('Parent alias') => $resource->getParent()?->getAlias() ?? '',
                _('FQDN / Address') => $resource->getFqdn() ?? '',
                _('Monitoring Server') => $resource->getMonitoringServerName() ?? '',
                _('Notif') => $resource->isNotificationEnabled() ? _('Enabled') : ('Disabled'),
                _('Check') => _($this->getResourceCheck($resource)),
            ];
        }
    }

    /**
     * @param array<string> $filteredColumns
     *
     * @return array<string>
     */
    private function setHeaderByFilteredColumns(array $filteredColumns): array
    {
        $header = [
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
            'monitoring_server_name' => _('Monitoring Server'),
            'notification' => _('Notif'),
            'checks' => _('Check'),
        ];

        return array_filter($header, function ($key) use ($filteredColumns) {
            // if the key is a resource or parent_resource, we keep all columns starting with this key
            if (str_starts_with($key, 'resource_')) {
                $key = 'resource';
            } else {
                if (str_starts_with($key, 'parent_resource_')) {
                    $key = 'parent_resource';
                }
            }

            return in_array($key, $filteredColumns);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param \Traversable<array<string,mixed>> $csvResources
     * @param array $columns
     *
     * @return \Traversable<array<string,mixed>>
     */
    private function filterColumns(\Traversable $csvResources, array $columns): \Traversable
    {
        foreach ($csvResources as $resource) {
            yield array_filter($resource, function ($key) use ($columns) {
                return in_array($key, $columns);
            }, ARRAY_FILTER_USE_KEY);
        }
    }

    /**
     * @param string $label
     *
     * @return string
     */
    private function formatLabel(string $label): string
    {
        return ucfirst(strtolower($label));
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
            $notes = $notes ? $this->formatUrl($notes) : null;
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
     * @param \DateTime|null $date
     *
     * @return string
     */
    private function formatDate(?\DateTime $date): string
    {
        return ! is_null($date) ? $date->format($this->contact->getFormatDate()) : '';
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function formatUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        // FIXME replace by the good base url
        $baseUrl = 'https://www.centreon.com';
        if (! str_starts_with($baseUrl, $url)) {
            if (! str_starts_with($url, '/')) {
                $url = '/' . $url;
            }

            return $baseUrl . $url;
        }

        return $url;
    }

}
