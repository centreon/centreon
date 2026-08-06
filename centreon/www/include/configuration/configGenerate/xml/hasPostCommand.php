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

/**
 * Lightweight endpoint used by the modern "Export configuration" page.
 * Given a list of poller ids, it returns for each whether a post-generation
 * command is configured, so the UI can show/skip the "Post-command" step.
 * Response: {"<pollerId>": true|false, ...}
 */

use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Core\Contact\Domain\AdminResolver;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

if (! isset($_POST['poller'])) {
    exit();
}

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonInstance.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';

$db = new CentreonDB();
$kernel = App\Kernel::createForWeb();
CentreonSession::start(1);

$readAccessGroupRepository = $kernel->getContainer()->get(ReadAccessGroupRepositoryInterface::class);
$isCloudPlatform = $kernel->getContainer()->getParameter('env(IS_CLOUD_PLATFORM)');
$adminResolver = new AdminResolver($readAccessGroupRepository, $isCloudPlatform);
$readContactRepository = $kernel->getContainer()->get(ContactRepositoryInterface::class);
$contact = $readContactRepository->findBySession(session_id());

// Check Session
if (
    ! CentreonSession::checkSession(session_id(), $db)
    || $contact === null
    || (! $adminResolver->isAdmin($contact) && ! $contact->hasRole('ROLE_GENERATE_CONFIGURATION'))
) {
    header('Content-Type: application/json');
    echo json_encode([]);

    exit();
}

$pollers = array_filter(array_map('intval', explode(',', $_POST['poller'])));
$instanceObj = new CentreonInstance($db);

$result = [];
foreach ($pollers as $pollerId) {
    $result[$pollerId] = count($instanceObj->getCommandData($pollerId)) > 0;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode($result);
