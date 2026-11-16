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

namespace Centreon\Infrastructure\Monitoring\Metric;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Centreon\Domain\Monitoring\Service;
use Core\Metric\Application\Repository\ReadMetricsStorageSettingsRepositoryInterface;

/**
 * Reads the graphs from the RRD files (default) or from VictoriaMetrics, after the option
 * "metrics_data_source". The repositories are built on first use: the RRD one opens its database
 * connections in its constructor.
 */
final class MetricRepositorySelector implements MetricRepositoryInterface
{
    private ?ContactInterface $contact = null;

    private ?MetricRepositoryInterface $repository = null;

    /**
     * @param ReadMetricsStorageSettingsRepositoryInterface $settingsRepository
     * @param \Closure(): MetricRepositoryInterface $rrdRepository
     * @param \Closure(): MetricRepositoryInterface $victoriaMetricsRepository
     */
    public function __construct(
        private readonly ReadMetricsStorageSettingsRepositoryInterface $settingsRepository,
        private readonly \Closure $rrdRepository,
        private readonly \Closure $victoriaMetricsRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function setContact(ContactInterface $contact): MetricRepositoryInterface
    {
        $this->contact = $contact;
        $this->repository?->setContact($contact);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function findMetricsByService(Service $service, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->getRepository()->findMetricsByService($service, $start, $end);
    }

    /**
     * @inheritDoc
     */
    public function findStatusByService(Service $service, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->getRepository()->findStatusByService($service, $start, $end);
    }

    /**
     * @throws \Throwable
     */
    private function getRepository(): MetricRepositoryInterface
    {
        if ($this->repository === null) {
            $this->repository = $this->settingsRepository->find()->isVictoriaMetrics()
                ? ($this->victoriaMetricsRepository)()
                : ($this->rrdRepository)();
            if ($this->contact !== null) {
                $this->repository->setContact($this->contact);
            }
        }

        return $this->repository;
    }
}
