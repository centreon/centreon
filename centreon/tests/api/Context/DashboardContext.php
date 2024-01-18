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

namespace Centreon\Test\Api\Context;

use Centreon\Test\Api\Context\FeatureFlagContext;

class DashboardContext extends FeatureFlagContext
{
    /**
     * Wait to get metrics from metrics data endpoint
     *
     * @param int $count expected count of metrics
     * @param string $url the listing endpoint
     * @param int $tries Count of tries
     * @return int the count of metrics
     *
     * @Given /^I wait to get (\d+) metrics? from ['"](\S+)['"](?: \(tries: (\d+)\))?$/
     */
    public function iWaitToGetSomeMetricsFrom(int $count, string $url, int $tries = 15): int
    {
        $metricsCount = 0;

        $url = $this->replaceCustomVariables($url);

        $this->spin(
            function() use ($count, $url, &$metricsCount) {
                $response = $this->iSendARequestTo('GET', $url);
                $response = json_decode($response->getBody()->__toString(), true);
                $metricsCount = count($response["metrics"]);
                $this->theJsonNodeShouldHaveAtLeastElements('metrics', $count);

                return true;
            },
            'the count of result(s) is : ' . $metricsCount,
            $tries
        );

        return $metricsCount;
    }
}
