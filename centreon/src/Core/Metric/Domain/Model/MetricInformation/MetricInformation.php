<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Metric\Domain\Model\MetricInformation;

class MetricInformation
{
    public function __construct(
        private readonly GeneralInformation $generalInformation,
        private readonly DataSource $dataSource,
        private readonly ThresholdInformation $thresholdInformation,
        private readonly RealTimeDataInformation $realTimeDataInformation
    ) {
    }

    public function getGeneralInformation(): GeneralInformation
    {
        return $this->generalInformation;
    }

    public function getDataSource(): DataSource
    {
        return $this->dataSource;
    }

    public function getThresholdInformation(): ThresholdInformation
    {
        return $this->thresholdInformation;
    }

    public function getRealTimeDataInformation(): RealTimeDataInformation
    {
        return $this->realTimeDataInformation;
    }
}
