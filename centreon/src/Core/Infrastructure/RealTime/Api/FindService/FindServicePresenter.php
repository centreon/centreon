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

namespace Core\Infrastructure\RealTime\Api\FindService;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\RealTime\UseCase\FindService\FindServicePresenterInterface;
use Core\Application\RealTime\UseCase\FindService\FindServiceResponse;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Infrastructure\RealTime\Hypermedia\HypermediaCreator;

class FindServicePresenter extends AbstractPresenter implements FindServicePresenterInterface
{
    use PresenterTrait;
    use HttpUrlTrait;

    /**
     * @param HypermediaCreator $hypermediaCreator
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private HypermediaCreator $hypermediaCreator,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * {@inheritDoc}
     *
     * @param FindServiceResponse $data
     */
    public function present(mixed $data): void
    {
        $presenterResponse = [
            'uuid' => 'h' . $data->hostId . '-s' . $data->serviceId,
            'id' => $data->serviceId,
            'name' => $data->name,
            'type' => 'service',
            'short_type' => 's',
            'status' => $data->status,
            'in_downtime' => $data->isInDowntime,
            'acknowledged' => $data->isAcknowledged,
            'flapping' => $data->isFlapping,
            'performance_data' => $data->performanceData,
            'information' => $data->output,
            'command_line' => $data->commandLine,
            'notification_number' => $data->notificationNumber,
            'latency' => $data->latency,
            'percent_state_change' => $data->statusChangePercentage,
            'passive_checks' => $data->hasPassiveChecks,
            'execution_time' => $data->executionTime,
            'active_checks' => $data->hasActiveChecks,
            'icon' => $data->icon,
            'groups' => $this->hypermediaCreator->convertGroupsForPresenter($data),
            'parent' => $data->host,
            'monitoring_server_name' => $data->host['monitoring_server_name'],
            'categories' => $this->hypermediaCreator->convertCategoriesForPresenter($data),
            'severity' => $data->severity,
        ];

        if (! empty($data->severity['icon'])) {
            /**
             * normalize the URL to the severity icon.
             */
            $presenterResponse['severity']['icon']['url'] = $this->getBaseUri()
            . '/img/media/' . $data->severity['icon']['url'];
        }

        $acknowledgement = null;

        if (! empty($data->acknowledgement)) {
            /**
             * Convert Acknowledgement dates into ISO 8601 format.
             */
            $acknowledgement = $data->acknowledgement;
            $acknowledgement['entry_time'] = $this->formatDateToIso8601($data->acknowledgement['entry_time']);
            $acknowledgement['deletion_time'] = $this->formatDateToIso8601($data->acknowledgement['deletion_time']);
        }

        $presenterResponse['acknowledgement'] = $acknowledgement;

        /**
         * Convert downtime dates into ISO 8601 format.
         */
        $formattedDatesDowntimes = [];

        foreach ($data->downtimes as $key => $downtime) {
            $formattedDatesDowntimes[$key] = $downtime;
            $formattedDatesDowntimes[$key]['start_time'] = $this->formatDateToIso8601($downtime['start_time']);
            $formattedDatesDowntimes[$key]['end_time'] = $this->formatDateToIso8601($downtime['end_time']);
            $formattedDatesDowntimes[$key]['actual_start_time']
                = $this->formatDateToIso8601($downtime['actual_start_time']);
            $formattedDatesDowntimes[$key]['actual_end_time']
                = $this->formatDateToIso8601($downtime['actual_end_time']);
            $formattedDatesDowntimes[$key]['entry_time'] = $this->formatDateToIso8601($downtime['entry_time']);
            $formattedDatesDowntimes[$key]['deletion_time'] = $this->formatDateToIso8601($downtime['deletion_time']);
        }

        $presenterResponse['downtimes'] = $formattedDatesDowntimes;

        /**
         * Calculate the duration.
         */
        $presenterResponse['duration'] = $data->lastStatusChange !== null
            ? \CentreonDuration::toString(time() - $data->lastStatusChange->getTimestamp())
            : null;

        /**
         * Convert dates to ISO 8601 format.
         */
        $presenterResponse['next_check'] = $this->formatDateToIso8601($data->nextCheck);
        $presenterResponse['last_check'] = $this->formatDateToIso8601($data->lastCheck);
        $presenterResponse['last_time_with_no_issue'] = $this->formatDateToIso8601($data->lastTimeOk);
        $presenterResponse['last_status_change'] = $this->formatDateToIso8601($data->lastStatusChange);
        $presenterResponse['last_notification'] = $this->formatDateToIso8601($data->lastNotification);

        /**
         * Creating the 'tries' entry.
         */
        $tries = $data->checkAttempts . '/' . $data->maxCheckAttempts;
        $statusType = $data->status['type'] === 0 ? 'S' : 'H';
        $presenterResponse['tries'] = $tries . '(' . $statusType . ')';

        /**
         * Creating Hypermedias.
         */
        $parameters = [
            'type' => $data->type,
            'hostId' => $data->hostId,
            'serviceId' => $data->serviceId,
            'hasGraphData' => $data->hasGraphData,
        ];

        $endpoints = $this->hypermediaCreator->createEndpoints($parameters);

        $presenterResponse['links']['endpoints'] = [
            'notification_policy' => $endpoints['notification_policy'],
            'timeline' => $endpoints['timeline'],
            'timeline_download' => $endpoints['timeline_download'],
            'status_graph' => $endpoints['status_graph'],
            'performance_graph' => $endpoints['performance_graph'],
            'details' => $endpoints['details'],
            'metrics' => $endpoints['metrics'],
        ];

        $presenterResponse['links']['uris'] = $this->hypermediaCreator->createInternalUris($parameters);

        parent::present($presenterResponse);
    }
}
