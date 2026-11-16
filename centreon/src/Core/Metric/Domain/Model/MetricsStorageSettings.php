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

namespace Core\Metric\Domain\Model;

/**
 * Data source of the graphs and connection to VictoriaMetrics (options of Administration > Parameters).
 */
final class MetricsStorageSettings
{
    public function __construct(
        private readonly MetricsDataSource $dataSource = MetricsDataSource::Rrd,
        private readonly string $victoriaMetricsUrl = '',
        private readonly string $victoriaMetricsUser = '',
        private readonly string $victoriaMetricsPassword = '',
    ) {
    }

    public function getDataSource(): MetricsDataSource
    {
        return $this->dataSource;
    }

    public function isVictoriaMetrics(): bool
    {
        return $this->dataSource === MetricsDataSource::VictoriaMetrics;
    }

    /**
     * @return string base URL, without trailing slash (empty when not configured)
     */
    public function getVictoriaMetricsUrl(): string
    {
        return rtrim(trim($this->victoriaMetricsUrl), '/');
    }

    public function getVictoriaMetricsUser(): string
    {
        return $this->victoriaMetricsUser;
    }

    public function getVictoriaMetricsPassword(): string
    {
        return $this->victoriaMetricsPassword;
    }
}
