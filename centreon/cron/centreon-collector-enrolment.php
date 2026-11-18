<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

// Every minute (feature flag collector_enrolment): pollers of fleet enrolments, first
// configuration of the collectors that connected, removal of the nodes of deleted pollers.

require_once realpath(__DIR__ . '/../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/cron/checkConfiguration.php';
require_once _CENTREON_PATH_ . '/bootstrap.php';

use Core\Gorgone\Application\Service\CollectorEnrolmentService;

$service = App\Kernel::createForWeb()->getContainer()->get('legacy.service_locator')->get('collector_enrolment_service');
if (! $service instanceof CollectorEnrolmentService || ! $service->isEnabled()) {
    exit(0);
}

try {
    foreach ($service->synchronize() as $line) {
        echo date('Y-m-d H:i:s') . ' collector enrolment: ' . $line . "\n";
    }
} catch (Throwable $ex) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_BUSINESS_LOG,
        'Collector enrolment CRON: ' . $ex->getMessage(),
        exception: $ex
    );
    fwrite(STDERR, date('Y-m-d H:i:s') . ' collector enrolment failed: ' . $ex->getMessage() . "\n");

    exit(1);
}
