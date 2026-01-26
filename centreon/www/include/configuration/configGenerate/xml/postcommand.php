<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Core\Contact\Domain\AdminResolver;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

if (! isset($_POST['poller'])) {
    exit();
}

require_once realpath(dirname(__FILE__) . "/../../../../../config/centreon.config.php");
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonXML.class.php';
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
    echo 'Bad Session';

    exit();
}

// Check CSRF token
if (! isset($_POST['centreon_token']) || ! isCSRFTokenValid()) {
    echo json_encode(['error' => 'Invalid security token']);

    exit;
}
purgeCSRFToken();

$pollers = explode(',', $_POST['poller']);

$xml = new CentreonXML();
$gorgoneService = $kernel->getContainer()->get(Centreon\Domain\Gorgone\Interfaces\GorgoneServiceInterface::class);

$res = $db->query("SELECT `id` FROM `nagios_server` WHERE `localhost` = '1'");
$idCentral = (int)$res->fetch(\PDO::FETCH_COLUMN);

$res = $db->query("SELECT `name`, `id`, `localhost`
    FROM `nagios_server`
    WHERE `ns_activate` = '1'
    ORDER BY `name` ASC");
$xml->startElement('response');
$str = sprintf("<br/><b>%s</b><br/>", _("Post execution command results"));
$ok = true;
$instanceObj = new CentreonInstance($db);

while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
    if ($pollers == 0 || in_array($row['id'], $pollers)) {
        $commands = $instanceObj->getCommandData($row['id']);
        if (!count($commands)) {
            continue;
        }
        $str .= "<br/><strong>{$row['name']}</strong><br/>";
        foreach ($commands as $command) {
            $requestData = json_encode(
                [
                    [
                        "command" => $command['command_line'],
                        "timeout" => 30,
                        "continue_on_error" => true
                    ]
                ]
            );
            $gorgoneCommand = new \Centreon\Domain\Gorgone\Command\Command($idCentral, $requestData);
            $gorgoneResponse = $gorgoneService->send($gorgoneCommand);
            $str .= _("Executing command") . ": " . $command['command_name'] . "<br/>";
        }
    }
}
if ($ok === false) {
    $statusStr = "<b><font color='red'>NOK</font></b>";
} else {
    $statusStr = "<b><font color='green'>OK</font></b>";
}

$xml->writeElement('result', $str);
$xml->writeElement('status', $statusStr);
$xml->endElement();
header('Content-Type: application/xml');
header('Cache-Control: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');
$xml->output();
