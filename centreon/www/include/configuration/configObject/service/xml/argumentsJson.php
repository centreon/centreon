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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

require_once realpath(__DIR__ . '/../../../../../../config/centreon.config.php');

// PSR-4 autoloader for the src/ classes used below (QueryParameters, Logger):
// this standalone endpoint does not go through the full bootstrap.
require_once realpath(__DIR__ . '/../../../../../../vendor/autoload.php');

require_once __DIR__ . '/argumentsXmlFunction.php';

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';

// Get session
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';

if (! isset($_SESSION['centreon'])) {
    CentreonSession::start(1);
}

if (isset($_SESSION['centreon'])) {
    $oreon = $_SESSION['centreon'];
} else {
    // A bare exit would answer 200 with an empty body, which the caller reads
    // as "this command has no arguments" — the same state as a successful
    // empty response, on a form that then saves those arguments away.
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Session expired'], JSON_THROW_ON_ERROR);

    exit;
}

// The endpoint hands back persisted command arguments for the ids it is given,
// so reaching one of the three pages that embed the form is the minimum. The
// form is included by formService.php (60201 services by host, 60202 by host
// group) and by formServiceTemplateModel.php (60206).
if (! $oreon->user->admin) {
    $access = $oreon->user->access;
    $reachesForm = $access !== null
        && ($access->page(60201) !== 0 || $access->page(60202) !== 0 || $access->page(60206) !== 0);

    if (! $reachesForm) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Access denied'], JSON_THROW_ON_ERROR);

        exit;
    }
}

// Get language
$locale = $oreon->user->get_lang();
putenv("LANG={$locale}");
setlocale(LC_ALL, $locale);
bindtextdomain('messages', _CENTREON_PATH_ . 'www/locale/');
bind_textdomain_codeset('messages', 'UTF-8');
textdomain('messages');

// start init db
$db = new CentreonDB();

$args = [];

try {
    if (isset($_GET['cmdId'], $_GET['svcId'], $_GET['svcTplId'], $_GET['o'])) {
        $cmdId    = (int) filter_var($_GET['cmdId'], FILTER_VALIDATE_INT);
        $svcId    = (int) filter_var($_GET['svcId'], FILTER_VALIDATE_INT);
        $svcTplId = (int) filter_var($_GET['svcTplId'], FILTER_VALIDATE_INT);
        $mode     = $_GET['o'];

        // Page access alone would let any id be probed, so the service is also
        // checked against the caller ACL. Templates are skipped deliberately:
        // service_register = '0' rows carry no centreon_acl entry of their own,
        // and the template chain is only reachable through the form the page
        // check above already gates.
        if ($svcId !== 0 && ! $oreon->user->admin) {
            $isMonitoredService = (bool) $db->fetchOne(
                <<<'SQL'
                    SELECT 1 FROM `service`
                    WHERE service_id = :svcId AND service_register = '1'
                    LIMIT 1
                    SQL,
                QueryParameters::create([QueryParameter::int('svcId', $svcId)])
            );

            if ($isMonitoredService) {
                $groupIds = array_values(array_filter(array_map(
                    'intval',
                    array_keys($oreon->user->access->getAccessGroups())
                )));

                $isGranted = false;
                if ($groupIds !== []) {
                    $aclDbName       = $db->getConnectionConfig()->getDatabaseNameRealTime();
                    $aclPlaceholders = [];
                    $aclParameters   = [QueryParameter::int('svcId', $svcId)];
                    foreach ($groupIds as $index => $groupId) {
                        $placeholder       = 'acl_gid' . $index;
                        $aclPlaceholders[] = ':' . $placeholder;
                        $aclParameters[]   = QueryParameter::int($placeholder, $groupId);
                    }
                    $aclIn = implode(', ', $aclPlaceholders);

                    $isGranted = (bool) $db->fetchOne(
                        <<<SQL
                            SELECT 1 FROM `{$aclDbName}`.centreon_acl
                            WHERE service_id = :svcId AND group_id IN ({$aclIn})
                            LIMIT 1
                            SQL,
                        QueryParameters::create($aclParameters)
                    );
                }

                if (! $isGranted) {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied'], JSON_THROW_ON_ERROR);

                    exit;
                }
            }
        }

        // No command on the service itself: walk up the template chain until one
        // defines a check command. $visited guards against a cyclic chain.
        $visited = [];
        while ($cmdId === 0 && $svcTplId !== 0) {
            $template = $db->fetchAssociative(
                <<<'SQL'
                    SELECT service_template_model_stm_id, command_command_id
                    FROM `service`
                    WHERE service_id = :svcTplId
                    SQL,
                QueryParameters::create([QueryParameter::int('svcTplId', $svcTplId)])
            );

            if ($template === false || $template === []) {
                break;
            }

            if (! empty($template['command_command_id'])) {
                $cmdId = (int) $template['command_command_id'];
                break;
            }

            $parentId = (int) ($template['service_template_model_stm_id'] ?? 0);
            if ($parentId === 0 || isset($visited[$parentId])) {
                break;
            }

            $visited[$parentId] = true;
            $svcTplId = $parentId;
        }

        $argTab = [];
        $exampleTab = [];
        $valueTab = [];

        $command = $db->fetchAssociative(
            <<<'SQL'
                SELECT command_line, command_example FROM command WHERE command_id = :cmdId LIMIT 1
                SQL,
            QueryParameters::create([QueryParameter::int('cmdId', $cmdId)])
        );

        if ($command !== false && $command !== []) {
            preg_match_all('/\\$(ARG[0-9]+)\\$/', (string) $command['command_line'], $matches);
            foreach ($matches[1] as $value) {
                $argTab[$value] = $value;
            }

            $exampleTab = preg_split('/\!/', (string) $command['command_example']);
            if (is_array($exampleTab)) {
                foreach ($exampleTab as $key => $value) {
                    $exampleTab['ARG' . $key] = $value;
                    unset($exampleTab[$key]);
                }
            }
        }

        $serviceArgs = $db->fetchOne(
            <<<'SQL'
                SELECT command_command_id_arg FROM service WHERE service_id = :svcId LIMIT 1
                SQL,
            QueryParameters::create([QueryParameter::int('svcId', $svcId)])
        );

        if ($serviceArgs !== false) {
            $valueTab = preg_split('/(?<!\\\)\!/', (string) $serviceArgs);
            if (is_array($valueTab)) {
                foreach ($valueTab as $key => $value) {
                    $valueTab['ARG' . $key] = $value;
                    unset($valueTab[$key]);
                }
            } else {
                $exampleTab = [];
            }
        }

        foreach (
            $db->fetchAllAssociative(
                <<<'SQL'
                    SELECT macro_name, macro_description
                    FROM command_arg_description
                    WHERE cmd_id = :cmdId
                    ORDER BY macro_name
                    SQL,
                QueryParameters::create([QueryParameter::int('cmdId', $cmdId)])
            ) as $macro
        ) {
            $argTab[$macro['macro_name']] = $macro['macro_description'];
        }

        $disabled = $mode === 'w';
        foreach ($argTab as $name => $description) {
            $args[] = [
                'name' => $name,
                'description' => $description,
                'value' => $valueTab[$name] ?? '',
                'example' => isset($exampleTab[$name]) ? myDecodeValue($exampleTab[$name]) : '',
                'disabled' => $disabled,
            ];
        }
    }

    header('Content-Type: application/json');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: no-cache, must-revalidate');
    // Deliberately NOT JSON_INVALID_UTF8_SUBSTITUTE here, unlike the listings:
    // 'value' and 'example' are round-tripped back into command_command_id_arg
    // when the form is saved, so substituting a byte would silently rewrite the
    // stored argument. A bad byte is reported instead, and the client blocks the
    // save rather than writing something the user never typed.
    try {
        $payload = json_encode(['args' => $args], JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        Logger::create(LogChannelEnum::WEB)->error(
            'Service form: command arguments hold bytes that are not valid UTF-8',
            ['service_id' => $svcId]
        );
        http_response_code(409);
        echo json_encode(['error' => 'invalid_encoding'], JSON_THROW_ON_ERROR);

        exit;
    }

    echo $payload;
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'Service form: failed to fetch the command arguments',
        ['exception' => $exception]
    );

    http_response_code(500);
    echo json_encode(['error' => 'Internal error'], JSON_THROW_ON_ERROR);

    exit;
}
