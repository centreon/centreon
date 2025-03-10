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

        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                $this->exceptionHandler->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }

        $this->viewModel->setResources($this->transformToCsv($response->getResources()));
    }

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
                _('Status') => _($resource->getStatus()?->getName() ?? ''),
                _('Parent Resource Type') => _($this->formatLabel($resource->getParent()?->getType() ?? '')),
                _('Parent Resource Name') => _($resource->getParent()?->getName() ?? ''),
                _('Parent Resource Status') => _($this->formatLabel($resource->getParent()?->getStatus()?->getName() ?? '')),
                _('Duration') => $resource->getDuration() ?? '',
                _('Last Check') => $this->formatDate($resource->getLastCheck()),
                _('Information') => $resource->getInformation() ?? '',
                _('Tries') => $resource->getTries() ?? '',
                _('Severity') => $resource->getSeverity()?->getLevel() ?? '',
                _('Notes') => $this->getResourceNotes($resource),
                _('Action') => $resource->getLinks()->getExternals()->getActionUrl() ?? '',
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
     * @return ExportResourcesViewModel
     */
    public function getViewModel(): ExportResourcesViewModel
    {
        return $this->viewModel;
    }

    // ---------------------------------- PRIVATE METHODS ---------------------------------- //

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
            $state[] = 'Downtime';
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
    private function formatDate(?\DateTime $date): string {
        return ! is_null($date) ? $date->format($this->contact->getFormatDate()) : '';
    }
}
