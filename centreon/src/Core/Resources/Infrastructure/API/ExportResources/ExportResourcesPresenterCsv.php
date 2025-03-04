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

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionHandler;
use Core\Infrastructure\Common\Presenter\CsvFormatter;
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
    private const EXPORT_VIEW_TYPE = 'all';

    /**
     * ExportResourcesPresenterCsv constructor
     *
     * @param CsvFormatter $presenterFormatter
     * @param ExceptionHandler $exceptionHandler
     */
    public function __construct(
        CsvFormatter $presenterFormatter,
        private readonly ExceptionHandler $exceptionHandler
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
        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && $response->hasException()) {
                $this->exceptionHandler->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }

        // modify headers to download a csv file
        $this->setResponseHeaders(
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $this->getCsvFileName() . '"',
            ]
        );

        $this->present($this->transformToCsv($response->getResources()));
    }

    /**
     * @param \Traversable $resources
     *
     * @return \Traversable
     */
    private function transformToCsv(\Traversable $resources): \Traversable
    {
        foreach ($resources as $resource) {
            yield [
                'Status' => $resource->getStatus()?->getName() ?? '',
                'Resource Type' => $resource->getType() ?? '',
                'Parent resource status' => $resource->getParent()?->getStatus()?->getName() ?? '',
                'Duration' => $resource->getDuration() ?? '',
                'Last Check' => $resource->getLastCheck()?->format('Y-m-d H:i:s') ?? '',
                'Information' => $resource->getInformation() ?? '',
                'Tries' => $resource->getTries() ?? '',
                'Severity' => $resource->getSeverity()?->getName() ?? '',
                'Notes' => '',
                'Action' => '',
                'State' => '',
                'Alias' => $resource->getAlias() ?? '',
                'Parent alias' => $resource->getParent()?->getAlias() ?? '',
                'FQDN / Address' => '',
                'Monitoring Server' => $resource->getMonitoringServerName(),
                'Check' => '',
            ];
        }
    }

    /**
     * @param string $viewType
     *
     * @return string
     */
    private function getCsvFileName(string $viewType = self::EXPORT_VIEW_TYPE): string
    {
        return "ResourceStatusExport_{$viewType}_{$this->getDateFormatted()}.csv";
    }

    /**
     * @param string $format
     *
     * @return string
     */
    private function getDateFormatted(string $format = 'Y/m/d_H:i'): string
    {
        return (new \DateTime('now'))->format($format);
    }
}
