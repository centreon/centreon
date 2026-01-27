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

use App\Kernel;
use Core\Engine\Application\Repository\EngineRepositoryInterface;
use Core\Installation\Infrastructure\InstallationHelper;

set_time_limit(0);
require __DIR__ . '/../../../../vendor/autoload.php';

$return = [];

$kernel = Kernel::createForWeb();
/** @var InstallationHelper $installationHelper */
$installationHelper = $kernel->getContainer()->get(InstallationHelper::class);

/** @var EngineRepositoryInterface $engineRepository */
$engineRepository = $kernel->getContainer()->get(EngineRepositoryInterface::class);

try {
    // This file should not be touched if it has content.
    if ($engineRepository->engineSecretsHasContent() === false) {
        $installationHelper->writeEngineContextFile();
    }

    $return['result'] = 0;
    $return['msg'] = 'OK';
} catch (Throwable $ex) {
    $return['result'] = 1;
    $return['msg'] = $ex->getMessage();
}
echo json_encode($return);

exit;
