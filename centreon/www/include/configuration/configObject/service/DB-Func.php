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

if (! isset($centreon)) {
    exit();
}

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use App\Kernel;
use Centreon\Domain\Log\Logger;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\VaultEligibilityService;
use Core\Common\Infrastructure\Api\InternalApiClient;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Infrastructure\Common\Api\Router;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Utility\Interfaces\UUIDGeneratorInterface;

require_once _CENTREON_PATH_ . 'www/include/common/vault-functions.php';

/**
 * For ACL
 *
 * @param CentreonDB $db
 * @param int $hostId
 * @param null|mixed $hostgroupId
 * @return null
 */
function setHostChangeFlag($db, $hostId = null, $hostgroupId = null)
{
    if (isset($hostId)) {
        $table = 'acl_resources_host_relations';
        $field = 'host_host_id';
        $val = $hostId;
    } elseif (isset($hostgroupId)) {
        $table = 'acl_resources_hg_relations';
        $field = 'hg_hg_id';
        $val = $hostgroupId;
    } else {
        return null;
    }
    $query = 'UPDATE acl_resources SET changed = 1 '
        . 'WHERE acl_res_id IN ('
        . "SELECT acl_res_id FROM {$table} WHERE {$field} = :fieldValue)";
    $statement = $db->prepare($query);
    $statement->bindValue(':fieldValue', (int) $val, PDO::PARAM_INT);
    $statement->execute();

    return null;
}

/**
 * This is a quickform rule for checking if circular inheritance is used
 *
 * @return bool
 */
function checkCircularInheritance(int $templateId)
{
    global $form;

    $data = $form->getSubmitValues();
    if ((int) $data['service_id'] === $templateId) {
        return false;
    }

    $kernel = Kernel::createForWeb();
    $repository = $kernel->getContainer()->get(ReadServiceTemplateRepositoryInterface::class);
    $inheritanceArray = $repository->findParents($templateId);
    $parentsIds = array_map(
        static fn (ServiceTemplateInheritance $inheritancePair): int => $inheritancePair->getParentId(),
        $inheritanceArray
    );

    return ! (in_array((int) $data['service_id'], $parentsIds, true));
}

/**
 * Quickform rule that checks whether or not reserved macro are used
 *
 * @return bool
 */
function serviceMacHandler()
{
    global $pearDB;

    $macArray = $_POST;
    $macTab = [];
    foreach ($macArray as $key => $value) {
        if (isset($value) && is_string($value) && preg_match('/^macroInput/', $key, $matches)) {
            $macTab[] = '$_SERVICE' . strtoupper($value) . '$';
        }
    }
    if ($macTab !== []) {
        $placeholders = [];
        foreach ($macTab as $idx => $macName) {
            $placeholders[] = ':macro_' . $idx;
        }
        $sql = 'SELECT count(*) as nb FROM nagios_macro WHERE macro_name IN (' . implode(',', $placeholders) . ')';
        $statement = $pearDB->prepare($sql);
        foreach ($macTab as $idx => $macName) {
            $statement->bindValue(':macro_' . $idx, $macName);
        }
        $statement->execute();
        $row = $statement->fetch();
        if (isset($row['nb']) && $row['nb']) {
            return false;
        }
    }

    return true;
}

/**
 * This is a quickform rule for checking if all the argument fields are filled
 *
 * @return bool
 */
function argHandler()
{
    $argArray = $_POST;
    $argTab = [];
    foreach ($argArray as $key => $value) {
        if (preg_match('/^ARG(\d+)/', $key, $matches)) {
            $argTab[$matches[1]] = $value;
        }
    }
    $fill = false;
    $nofill = false;
    foreach ($argTab as $val) {
        if ($val != '') {
            $fill = true;
        } else {
            $nofill = true;
        }
    }

    return ! ($fill === true && $nofill === true);
}

/**
 * Returns the formatted string for command arguments
 *
 * @param $argArray
 * @param mixed $conf
 * @return string
 */
function getCommandArgs($argArray = [], $conf = [])
{
    if (isset($conf['command_command_id_arg'])) {
        return $conf['command_command_id_arg'];
    }
    $argTab = [];
    foreach ($argArray as $key => $value) {
        if (preg_match('/^ARG(\d+)/', $key, $matches)) {
            $argTab[$matches[1]] = $value;
            $argTab[$matches[1]] = str_replace("\n", '#BR#', $argTab[$matches[1]]);
            $argTab[$matches[1]] = str_replace("\t", '#T#', $argTab[$matches[1]]);
            $argTab[$matches[1]] = str_replace("\r", '#R#', $argTab[$matches[1]]);
        }
    }
    ksort($argTab);
    $str = '';
    foreach ($argTab as $val) {
        if ($val != '') {
            $str .= '!' . $val;
        }
    }
    if (! strlen($str)) {
        return null;
    }

    return $str;
}

function getHostServiceCombo($service_id = null, $service_description = null)
{
    global $pearDB;
    if ($service_id == null || $service_description == null) {
        return;
    }

    $statement = $pearDB->prepare(
        'SELECT h.host_name FROM host h, host_service_relation hsr '
        . 'WHERE h.host_id = hsr.host_host_id AND hsr.service_service_id = :serviceId LIMIT 1'
    );
    $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch();

    $combo = $row === false ? '- / ' . $service_description : $row['host_name'] . ' / ' . $service_description;

    return $combo;
}

function serviceExists($name = null)
{
    global $pearDB, $centreon;

    $statement = $pearDB->prepare(
        'SELECT service_description FROM service WHERE service_description = :name'
    );
    $statement->bindValue(':name', $centreon->checkIllegalChar($name));
    $statement->execute();

    return $statement->fetch() !== false;
}

/**
 * Test service template existence
 *
 * @param string $name
 * @param bool $returnId | whether function will return an id instead of boolean
 * @return mixed
 */
function testServiceTemplateExistence($name = null, $returnId = false)
{
    global $pearDB, $form, $centreon;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('service_id');
    }

    $statement = $pearDB->prepare(
        "SELECT service_description, service_id FROM service WHERE service_register = '0' AND service_description = :name"
    );
    $statement->bindValue(':name', $centreon->checkIllegalChar($name));
    $statement->execute();
    $service = $statement->fetch();
    $nbRows = $statement->rowCount();
    // Modif case
    if (isset($id)) {
        if ($nbRows >= 1 && $service['service_id'] == $id) {
            return true;
        }

        return ! ($nbRows >= 1 && $service['service_id'] != $id);  // Duplicate entry
    }

    return ! ($nbRows >= 1);
}

/**
 * Test service existence
 *
 * @param string $name
 * @param array $hPars
 * @param array $hgPars
 * @param bool $returnId | whether function will return an id instead of boolean
 * @param array $params
 * @return mixed
 */
function testServiceExistence($name = null, $hPars = [], $hgPars = [], $returnId = false, $params = [])
{
    global $pearDB, $centreon;
    global $form;

    $id = null;
    $hPars = (is_countable($hPars)) ? $hPars : [];
    $hgPars = (is_countable($hgPars)) ? $hgPars : [];

    if (isset($form) && ! count($hPars) && ! count($hgPars)) {
        $arr = count($params) ? $params : $form->getSubmitValues();
        if (isset($arr['service_id'])) {
            $id = $arr['service_id'];
        }
        $hPars = $arr['service_hPars'] ?? [];
        $hPars = is_array($hPars) ? $hPars : [$hPars];
        $hgPars = $arr['service_hgPars'] ?? [];
    }

    $escapeName = $centreon->checkIllegalChar($name);

    $statement = $pearDB->prepare(
        <<<'SQL'
            SELECT service.service_id
            FROM service
            INNER JOIN host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            WHERE hsr.host_host_id = :host_id
                AND service.service_description = :service_description
            SQL
    );
    foreach ($hPars as $hostId) {
        $statement->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
        $statement->bindValue(':service_description', $escapeName);
        $statement->execute();
        $service = $statement->fetch(PDO::FETCH_ASSOC);
        // Duplicate entry
        if ($statement->rowCount() >= 1 && $service['service_id'] != $id) {
            return ($returnId == false) ? false : $service['service_id'];
        }
    }

    $statement = $pearDB->prepare(
        <<<'SQL'
            SELECT service_id
            FROM service
            INNER JOIN host_service_relation hsr
                ON hsr.service_service_id = service_id
            WHERE hsr.hostgroup_hg_id = :hostgroup_hg_id
                AND service.service_description = :service_description
            SQL
    );
    foreach ($hgPars as $hostGroupId) {
        $statement->bindValue(':hostgroup_hg_id', (int) $hostGroupId, PDO::PARAM_INT);
        $statement->bindValue(':service_description', $escapeName);
        $statement->execute();
        $service = $statement->fetch(PDO::FETCH_ASSOC);
        // Duplicate entry
        if ($statement->rowCount() >= 1 && $service['service_id'] != $id) {
            return ($returnId == false) ? false : $service['service_id'];
        }
    }

    return ($returnId == false) ? true : 0;
}

/**
 * Form rule for the service "Mass Change": forbids moving one or several services onto a
 * host (or hostgroup) that already holds another service with the same description, and also
 * catches the case where two selected services sharing the same description are moved onto the
 * same host/hostgroup within the same mass change.
 *
 * @param array $fields submitted form values
 *
 * @return array|bool true when valid, otherwise an [elementName => errorMessage] array
 */
function checkServiceMassiveChangeExistence(array $fields): array|bool
{
    if (empty($fields['select'])) {
        return true;
    }

    $targetHosts = is_array($fields['service_hPars'] ?? null) ? $fields['service_hPars'] : [];
    $targetHostGroups = is_array($fields['service_hgPars'] ?? null) ? $fields['service_hgPars'] : [];

    if (! count($targetHosts) && ! count($targetHostGroups)) {
        return true;
    }

    $serviceIds = array_filter(array_map('intval', explode(',', (string) $fields['select'])));

    // Descriptions already claimed on each target relation within this mass change,
    // so two selected services with the same description cannot land on the same host.
    $seenOnHost = [];
    $seenOnHostGroup = [];

    foreach ($serviceIds as $serviceId) {
        $description = getServiceDescriptionById($serviceId);
        if ($description === null) {
            continue;
        }

        foreach ($targetHosts as $hostId) {
            $hostId = (int) $hostId;
            if (
                serviceDescriptionExistsOnHost($description, $hostId, $serviceId)
                || isset($seenOnHost[$hostId][$description])
            ) {
                return ['service_hPars' => _(
                    'One or more of the selected services cannot be moved: '
                    . 'a service with the same description already exists on the target host(s).'
                )];
            }
            $seenOnHost[$hostId][$description] = true;
        }

        foreach ($targetHostGroups as $hostGroupId) {
            $hostGroupId = (int) $hostGroupId;
            if (
                serviceDescriptionExistsOnHostGroup($description, $hostGroupId, $serviceId)
                || isset($seenOnHostGroup[$hostGroupId][$description])
            ) {
                return ['service_hgPars' => _(
                    'One or more of the selected services cannot be moved: '
                    . 'a service with the same description already exists on the target host group(s).'
                )];
            }
            $seenOnHostGroup[$hostGroupId][$description] = true;
        }
    }

    return true;
}

/**
 * @param int $serviceId
 *
 * @return string|null the service description, or null when the service does not exist
 */
function getServiceDescriptionById(int $serviceId): ?string
{
    global $pearDB;

    $statement = $pearDB->prepare(
        'SELECT service_description FROM service WHERE service_id = :service_id'
    );
    $statement->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
    $statement->execute();
    $description = $statement->fetchColumn();

    return $description === false ? null : $description;
}

/**
 * @param string $description
 * @param int $hostId
 * @param int $excludeServiceId service to exclude from the lookup (the one being moved)
 *
 * @return bool true when another service with the same description is already linked to the host
 */
function serviceDescriptionExistsOnHost(string $description, int $hostId, int $excludeServiceId): bool
{
    global $pearDB;

    $statement = $pearDB->prepare(
        <<<'SQL'
            SELECT 1
            FROM service
            INNER JOIN host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            WHERE hsr.host_host_id = :host_id
                AND service.service_description = :service_description
                AND service.service_id != :service_id
            SQL
    );
    $statement->bindValue(':host_id', $hostId, PDO::PARAM_INT);
    $statement->bindValue(':service_description', $description, PDO::PARAM_STR);
    $statement->bindValue(':service_id', $excludeServiceId, PDO::PARAM_INT);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

/**
 * @param string $description
 * @param int $hostGroupId
 * @param int $excludeServiceId service to exclude from the lookup (the one being moved)
 *
 * @return bool true when another service with the same description is already linked to the hostgroup
 */
function serviceDescriptionExistsOnHostGroup(string $description, int $hostGroupId, int $excludeServiceId): bool
{
    global $pearDB;

    $statement = $pearDB->prepare(
        <<<'SQL'
            SELECT 1
            FROM service
            INNER JOIN host_service_relation hsr
                ON hsr.service_service_id = service.service_id
            WHERE hsr.hostgroup_hg_id = :hostgroup_hg_id
                AND service.service_description = :service_description
                AND service.service_id != :service_id
            SQL
    );
    $statement->bindValue(':hostgroup_hg_id', $hostGroupId, PDO::PARAM_INT);
    $statement->bindValue(':service_description', $description, PDO::PARAM_STR);
    $statement->bindValue(':service_id', $excludeServiceId, PDO::PARAM_INT);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

/**
 * Get service id by combination of host or hostgroup relations
 *
 * @param string $serviceDescription
 * @param array $hPars
 * @param array $hgPars
 * @param mixed $params
 * @return int
 */
function getServiceIdByCombination($serviceDescription, $hPars = [], $hgPars = [], $params = [])
{
    if (! count($hPars) && ! count($hgPars)) {
        return testServiceTemplateExistence($serviceDescription, true);
    }

    return testServiceExistence($serviceDescription, $hPars, $hgPars, true, $params);
}

function enableServiceInDB($service_id = null, $service_arr = [])
{
    if (! $service_id && ! count($service_arr)) {
        return;
    }
    global $pearDB, $centreon;
    if ($service_id) {
        $service_arr = [$service_id => '1'];
    }

    $updateStatement = $pearDB->prepare("UPDATE service SET service_activate = '1' WHERE service_id = :serviceId");
    $selectStatement = $pearDB->prepare(
        'SELECT service_description FROM `service` WHERE service_id = :serviceId LIMIT 1'
    );
    foreach (array_keys($service_arr) as $serviceId) {
        if (filter_var($serviceId, FILTER_VALIDATE_INT) === false) {
            continue;
        }
        $updateStatement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
        $updateStatement->execute();

        $selectStatement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
        $selectStatement->execute();
        $serviceDescription = $selectStatement->fetchColumn();

        signalConfigurationChange('service', (int) $serviceId);
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_SERVICE,
            object_id: $serviceId,
            object_name: $serviceDescription,
            action_type: ActionLog::ACTION_TYPE_ENABLE
        );
    }
}

function disableServiceInDB($service_id = null, $service_arr = [])
{
    if (! $service_id && ! count($service_arr)) {
        return;
    }
    global $pearDB, $centreon;
    if ($service_id) {
        $service_arr = [$service_id => '1'];
    }
    $updateStatement = $pearDB->prepare("UPDATE service SET service_activate = '0' WHERE service_id = :serviceId");
    $selectStatement = $pearDB->prepare(
        'SELECT service_description FROM `service` WHERE service_id = :serviceId LIMIT 1'
    );
    foreach (array_keys($service_arr) as $serviceId) {
        if (filter_var($serviceId, FILTER_VALIDATE_INT) === false) {
            continue;
        }
        $updateStatement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
        $updateStatement->execute();

        $selectStatement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
        $selectStatement->execute();
        $row = $selectStatement->fetch();

        signalConfigurationChange('service', (int) $serviceId, [], false);
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_SERVICE,
            object_id: $serviceId,
            object_name: $row['service_description'],
            action_type: ActionLog::ACTION_TYPE_DISABLE
        );
    }
}

/**
 * @param int $serviceId
 */
function removeRelationLastServiceDependency(int $serviceId): void
{
    global $pearDB;

    $statement = $pearDB->prepare(
        'SELECT COUNT(dependency_dep_id) AS nb_dependency, dependency_dep_id AS id '
        . 'FROM dependency_serviceParent_relation '
        . 'WHERE dependency_dep_id = ('
        . 'SELECT dependency_dep_id FROM dependency_serviceParent_relation WHERE service_service_id = :serviceId'
        . ') GROUP BY dependency_dep_id'
    );
    $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
    $statement->execute();
    $result = $statement->fetch();
    if ($result !== false && $result['nb_dependency'] == 1) {
        $deleteStatement = $pearDB->prepare('DELETE FROM dependency WHERE dep_id = :depId');
        $deleteStatement->bindValue(':depId', (int) $result['id'], PDO::PARAM_INT);
        $deleteStatement->execute();
    }
}

/**
 * Delete service in DB
 *
 * Keep this method as service deletion by hostgroup is not supported in APIv2 for the moment.
 *
 * @param array<int, int> $services The list of service IDs to delete (Ids are the keys)
 */
function deleteServiceInDB(array $services = []): void
{
    global $pearDB, $centreon;

    $serviceIds = array_keys($services);
    $kernel = Kernel::createForWeb();
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    /** @var WriteVaultRepositoryInterface $writeVaultRepository */
    $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
    if ($vaultEligibilityService->shouldUseVault()) {
        deleteResourceSecretsInVault($writeVaultRepository, [], $serviceIds);
    }

    $updateTplStatement = $pearDB->prepare(
        'UPDATE service SET service_template_model_stm_id = NULL WHERE service_id = :service_id'
    );
    $selectChildrenStatement = $pearDB->prepare(
        'SELECT service_id FROM service WHERE service_template_model_stm_id = :serviceId'
    );
    $selectDescStatement = $pearDB->prepare(
        'SELECT service_description FROM `service` WHERE `service_id` = :serviceId LIMIT 1'
    );
    $deleteServiceStatement = $pearDB->prepare('DELETE FROM service WHERE service_id = :serviceId');
    $deleteMacroStatement = $pearDB->prepare('DELETE FROM on_demand_macro_service WHERE svc_svc_id = :serviceId');
    $deleteContactStatement = $pearDB->prepare(
        'DELETE FROM contact_service_relation WHERE service_service_id = :serviceId'
    );
    foreach ($serviceIds as $serviceId) {
        if (filter_var($serviceId, FILTER_VALIDATE_INT) === false) {
            continue;
        }
        $previousPollerIds = getPollersForConfigChangeFlagFromServiceId($serviceId);
        removeRelationLastServiceDependency((int) $serviceId);
        $selectChildrenStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $selectChildrenStatement->execute();
        while ($row = $selectChildrenStatement->fetch()) {
            $updateTplStatement->bindValue(':service_id', (int) $row['service_id'], PDO::PARAM_INT);
            $updateTplStatement->execute();
        }
        $selectDescStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $selectDescStatement->execute();
        $svcname = $selectDescStatement->fetch();
        $deleteServiceStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $deleteServiceStatement->execute();
        $deleteMacroStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $deleteMacroStatement->execute();
        $deleteContactStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $deleteContactStatement->execute();
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_SERVICE,
            object_id: $serviceId,
            object_name: $svcname['service_description'],
            action_type: ActionLog::ACTION_TYPE_DELETE
        );

        signalConfigurationChange('service', (int) $serviceId, $previousPollerIds);
    }
    $centreon->user->access->updateACL(['type' => 'SERVICE', 'id' => $serviceId, 'action' => 'DELETE']);
}

function divideGroupedServiceInDB($service_id = null, $service_arr = [], $toHost = null)
{
    global $pearDB, $pearDBO;

    if (! $service_id && ! count($service_arr)) {
        return;
    }

    if ($service_id) {
        $service_arr = [$service_id => '1'];
    }

    $countStatement = $pearDB->prepare(
        'SELECT count(host_host_id) as nbHost, count(hostgroup_hg_id) as nbHG '
        . 'FROM host_service_relation WHERE service_service_id = :serviceId'
    );
    $deleteSgStatement = $pearDB->prepare(
        'DELETE FROM servicegroup_relation WHERE service_service_id = :serviceId'
    );
    foreach ($service_arr as $key => $value) {
        $countStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
        $countStatement->execute();
        $res = $countStatement->fetch();

        if ($res['nbHost'] != 0 && $res['nbHG'] == 0) {
            divideHostsToHost($key);
        } elseif ($toHost) {
            divideHostGroupsToHost($key);
        } else {
            divideHostGroupsToHostGroup($key);
        }

        // Delete old links for servicegroups
        $deleteSgStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
        $deleteSgStatement->execute();

        // Flag service to delete
        $svcToDelete[$key] = 1;
    }

    // Purge Old Service
    $deleteSvcStatement = $pearDB->prepare('DELETE FROM service WHERE service_id = :serviceId');
    $deleteHsrStatement = $pearDB->prepare(
        'DELETE FROM host_service_relation WHERE service_service_id = :serviceId'
    );
    foreach ($svcToDelete as $svc_id => $flag) {
        $deleteSvcStatement->bindValue(':serviceId', (int) $svc_id, PDO::PARAM_INT);
        $deleteSvcStatement->execute();
        $deleteHsrStatement->bindValue(':serviceId', (int) $svc_id, PDO::PARAM_INT);
        $deleteHsrStatement->execute();
    }
}

function divideHostGroupsToHostGroup($service_id)
{
    global $pearDB, $pearDBO;

    $selectHgStatement = $pearDB->prepare(
        'SELECT hostgroup_hg_id FROM host_service_relation '
        . 'WHERE service_service_id = :serviceId AND hostgroup_hg_id IS NOT NULL'
    );
    $selectHgStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectHgStatement->execute();
    $dbResult3 = $selectHgStatement;
    $query = 'UPDATE index_data
              SET service_id = :sv_id
              WHERE host_id = :host_id AND service_id = :service_id';
    $statement = $pearDBO->prepare($query);
    while ($data = $dbResult3->fetch()) {
        $sv_id = multipleServiceInDB(
            [$service_id => '1'],
            [$service_id => '1'],
            null,
            0,
            $data['hostgroup_hg_id'],
            [],
            []
        );
        $hosts = getMyHostGroupHosts($data['hostgroup_hg_id']);
        foreach ($hosts as $host_id) {
            $statement->bindValue(':sv_id', (int) $sv_id, PDO::PARAM_INT);
            $statement->bindValue(':host_id', (int) $host_id, PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, $host_id, null);
        }
    }
    $dbResult3->closeCursor();
}

function divideHostGroupsToHost($service_id)
{
    global $pearDB, $pearDBO;

    $selectStatement = $pearDB->prepare(
        'SELECT host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id '
        . 'FROM host_service_relation WHERE service_service_id = :serviceId'
    );
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $query = 'UPDATE index_data
              SET service_id = :sv_id
              WHERE host_id = :host_id AND service_id = :service_id';
    $statement = $pearDBO->prepare($query);
    while ($relation = $selectStatement->fetch()) {
        $hosts = getMyHostGroupHosts($relation['hostgroup_hg_id']);

        foreach ($hosts as $host_id) {
            $sv_id = multipleServiceInDB(
                [$service_id => '1'],
                [$service_id => '1'],
                $host_id,
                0,
                null,
                [],
                [$relation['hostgroup_hg_id'] => null]
            );
            $statement->bindValue(':sv_id', (int) $sv_id, PDO::PARAM_INT);
            $statement->bindValue(':host_id', (int) $host_id, PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, $host_id, null);
        }
    }
    $selectStatement->closeCursor();
}

function divideHostsToHost($service_id)
{
    global $pearDB, $pearDBO;

    $selectStatement = $pearDB->prepare(
        'SELECT host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id '
        . 'FROM host_service_relation WHERE service_service_id = :serviceId'
    );
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $query = 'UPDATE index_data SET service_id = :sv_id WHERE host_id = :host_id AND service_id = :service_id';
    $statement = $pearDBO->prepare($query);
    while ($relation = $selectStatement->fetch()) {
        $sv_id = multipleServiceInDB(
            [$service_id => '1'],
            [$service_id => '1'],
            $relation['host_host_id'],
            0,
            null,
            [],
            [$relation['hostgroup_hg_id'] => null]
        );
        $statement->bindValue(':sv_id', (int) $sv_id, PDO::PARAM_INT);
        $statement->bindValue(':host_id', (int) $relation['host_host_id'], PDO::PARAM_INT);
        $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
        setHostChangeFlag($pearDB, $relation['host_host_id'], null);
    }
}

function multipleServiceInDB(
    $services = [],
    $nbrDup = [],
    $host = null,
    $descKey = 1,
    $hostgroup = null,
    $hPars = [],
    $hgPars = [],
    $params = [],
) {
    global $pearDB, $centreon;

    /* $descKey param is a flag. If 1, we know we have to rename description because it's a traditionnal
     duplication. If 0, we don't have to, beacause we duplicate services for an Host duplication */
    // Foreach Service
    $maxId = null;

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    $selectServiceStatement = $pearDB->prepare('SELECT * FROM service WHERE service_id = :serviceId LIMIT 1');
    foreach ($services as $key => $value) {
        if (filter_var($key, FILTER_VALIDATE_INT) === false) {
            continue;
        }
        // Get all information about it
        $selectServiceStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
        $selectServiceStatement->execute();
        $row = $selectServiceStatement->fetch();
        $row['service_id'] = null;

        // Loop on the number of Service we want to duplicate
        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($dupCount === false) {
            continue;
        }
        for ($i = 1; $i <= $dupCount; $i++) {
            // Build column list and values for parameterized INSERT
            $columns = [];
            $placeholders = [];
            $insertValues = [];
            $fields = [];
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                if ($key2 == 'service_description' && $descKey) {
                    $service_description = $value2 . '_' . $i;
                    $value2 = $value2 . '_' . $i;
                } elseif ($key2 == 'service_description') {
                    $service_description = null;
                }
                $columns[] = $key2;
                $placeholders[] = ':' . $key2;
                $insertValues[':' . $key2] = $value2;
                if ($key2 != 'service_id') {
                    $fields[$key2] = $value2;
                }
                if (isset($service_description)) {
                    $fields['service_description'] = $service_description;
                }
            }

            if (! count($hPars)) {
                $hPars = getMyServiceHosts($key);
            }
            if (! count($hgPars)) {
                $hgPars = getMyServiceHostGroups($key);
            }

            if (
                ($row['service_register'] && testServiceExistence($service_description, $hPars, $hgPars, $params))
                || (! $row['service_register'] && testServiceTemplateExistence($service_description))
            ) {
                $hPars = [];
                $hgPars = [];
                $hasValues = false;
                foreach ($insertValues as $v) {
                    if ($v !== null) {
                        $hasValues = true;
                        break;
                    }
                }
                if ($hasValues) {
                    $insertSql = 'INSERT INTO service (' . implode(', ', $columns) . ') VALUES ('
                        . implode(', ', $placeholders) . ')';
                    $insertStatement = $pearDB->prepare($insertSql);
                    foreach ($insertValues as $placeholder => $pValue) {
                        $insertStatement->bindValue(
                            $placeholder,
                            $pValue,
                            $pValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                        );
                    }
                    $insertStatement->execute();
                    $maxId = (int) $pearDB->lastInsertId();
                    if ($maxId !== 0) {
                        // Host duplication case -> Duplicate the Service for the Host we create
                        if ($host) {
                            $query = 'INSERT INTO host_service_relation
                                      VALUES (NULL, NULL, :host_id, NULL, :service_id)';
                            $statement = $pearDB->prepare($query);
                            $statement->bindValue(':host_id', (int) $host, PDO::PARAM_INT);
                            $statement->bindValue(':service_id', (int) $maxId, PDO::PARAM_INT);
                            $statement->execute();
                            setHostChangeFlag($pearDB, $host, null);
                        } elseif ($hostgroup) {
                            $query = 'INSERT INTO host_service_relation
                                      VALUES (NULL, :hostgroup_id, NULL,
                                              NULL, :service_id)';
                            $statement = $pearDB->prepare($query);
                            $statement->bindValue(':hostgroup_id', (int) $hostgroup, PDO::PARAM_INT);
                            $statement->bindValue(':service_id', (int) $maxId, PDO::PARAM_INT);
                            $statement->execute();
                            setHostChangeFlag($pearDB, null, $hostgroup);
                        } else {
                            // Service duplication case -> Duplicate the Service for each relation the base Service have
                            $selectHsrStatement = $pearDB->prepare(
                                'SELECT DISTINCT host_host_id, hostgroup_hg_id FROM host_service_relation '
                                . 'WHERE service_service_id = :serviceId'
                            );
                            $selectHsrStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                            $selectHsrStatement->execute();
                            $dbResult = $selectHsrStatement;
                            $fields['service_hPars'] = '';
                            $fields['service_hgPars'] = '';
                            $query = 'INSERT INTO host_service_relation
                                      VALUES (NULL, :hostgroup_hg_id, :host_host_id,
                                              NULL, :service_id)';
                            $statement = $pearDB->prepare($query);
                            while ($service = $dbResult->fetch()) {
                                if ($service['host_host_id']) {
                                    $statement->bindValue(
                                        ':hostgroup_hg_id',
                                        null,
                                        PDO::PARAM_NULL
                                    );
                                    $statement->bindValue(
                                        ':host_host_id',
                                        (int) $service['host_host_id'],
                                        PDO::PARAM_INT
                                    );
                                    $statement->bindValue(
                                        ':service_id',
                                        (int) $maxId,
                                        PDO::PARAM_INT
                                    );
                                    $statement->execute();
                                    setHostChangeFlag($pearDB, $service['host_host_id'], null);
                                    $fields['service_hPars'] .= $service['host_host_id'] . ',';
                                } elseif ($service['hostgroup_hg_id']) {
                                    $statement->bindValue(
                                        ':hostgroup_hg_id',
                                        (int) $service['hostgroup_hg_id'],
                                        PDO::PARAM_INT
                                    );
                                    $statement->bindValue(
                                        ':host_host_id',
                                        null,
                                        PDO::PARAM_NULL
                                    );
                                    $statement->bindValue(
                                        ':service_id',
                                        (int) $maxId,
                                        PDO::PARAM_INT
                                    );
                                    $statement->execute();
                                    setHostChangeFlag($pearDB, null, $service['hostgroup_hg_id']);
                                    $fields['service_hgPars'] .= $service['hostgroup_hg_id'] . ',';
                                }
                            }
                            $fields['service_hPars'] = trim($fields['service_hPars'], ',');
                            $fields['service_hgPars'] = trim($fields['service_hgPars'], ',');
                        }

                        // Contact duplication
                        $selectContactStatement = $pearDB->prepare(
                            'SELECT DISTINCT contact_id FROM contact_service_relation '
                            . 'WHERE service_service_id = :serviceId'
                        );
                        $selectContactStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectContactStatement->execute();
                        $dbResult = $selectContactStatement;
                        $fields['service_cs'] = '';
                        $query = 'INSERT INTO contact_service_relation VALUES (:service_id,:contact_id )';
                        $statement = $pearDB->prepare($query);
                        while ($C = $dbResult->fetch()) {
                            $statement->bindValue(':service_id', (int) $maxId, PDO::PARAM_INT);
                            $statement->bindValue(':contact_id', (int) $C['contact_id'], PDO::PARAM_INT);
                            $statement->execute();
                            $fields['service_cs'] .= $C['contact_id'] . ',';
                        }
                        $fields['service_cs'] = trim($fields['service_cs'], ',');

                        // ContactGroup duplication
                        $selectCgStatement = $pearDB->prepare(
                            'SELECT DISTINCT contactgroup_cg_id FROM contactgroup_service_relation '
                            . 'WHERE service_service_id = :serviceId'
                        );
                        $selectCgStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectCgStatement->execute();
                        $dbResult = $selectCgStatement;
                        $fields['service_cgs'] = '';
                        $query = 'INSERT INTO contactgroup_service_relation
                            VALUES (:contactgroup_cg_id,:service_id)';
                        $statement = $pearDB->prepare($query);
                        while ($Cg = $dbResult->fetch()) {
                            $statement->bindValue(
                                ':contactgroup_cg_id',
                                (int) $Cg['contactgroup_cg_id'],
                                PDO::PARAM_INT
                            );
                            $statement->bindValue(':service_id', (int) $maxId, PDO::PARAM_INT);
                            $statement->execute();
                            $fields['service_cgs'] .= $Cg['contactgroup_cg_id'] . ',';
                        }
                        $fields['service_cgs'] = trim($fields['service_cgs'], ',');

                        // Servicegroup duplication
                        $selectSgStatement = $pearDB->prepare(
                            'SELECT DISTINCT host_host_id, hostgroup_hg_id, servicegroup_sg_id '
                            . 'FROM servicegroup_relation WHERE service_service_id = :serviceId'
                        );
                        $selectSgStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectSgStatement->execute();
                        $dbResult = $selectSgStatement;
                        $fields['service_sgs'] = '';
                        $query = 'INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, '
                                 . 'service_service_id, servicegroup_sg_id)
                                 VALUES (:host_host_id,:hostgroup_hg_id,:service_service_id,:servicegroup_sg_id)';
                        $statement = $pearDB->prepare($query);
                        while ($Sg = $dbResult->fetch()) {
                            $host_id = isset($host) && $host ? $host : $Sg['host_host_id'] ?? null;
                            $hg_id = isset($hostgroup) && $hostgroup ? $hostgroup : $Sg['hostgroup_hg_id'] ?? null;
                            $statement->bindValue(
                                ':host_host_id',
                                $host_id,
                                $host_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT
                            );
                            $statement->bindValue(
                                ':hostgroup_hg_id',
                                $hg_id,
                                $hg_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT
                            );
                            $statement->bindValue(
                                ':service_service_id',
                                (int) $maxId,
                                PDO::PARAM_INT
                            );
                            $statement->bindValue(
                                ':servicegroup_sg_id',
                                $Sg['servicegroup_sg_id'],
                                PDO::PARAM_INT
                            );
                            $statement->execute();
                            if ($Sg['host_host_id']) {
                                $fields['service_sgs'] .= $Sg['host_host_id'] . ',';
                            }
                        }
                        $fields['service_sgs'] = trim($fields['service_sgs'], ',');

                        // Trap link duplication
                        $selectTrapsStatement = $pearDB->prepare(
                            'SELECT DISTINCT traps_id FROM traps_service_relation WHERE service_id = :serviceId'
                        );
                        $selectTrapsStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectTrapsStatement->execute();
                        $dbResult = $selectTrapsStatement;

                        $fields['service_traps'] = '';
                        $query = 'INSERT INTO traps_service_relation VALUES (:traps_id, :service_id)';
                        $statement = $pearDB->prepare($query);
                        while ($traps = $dbResult->fetch()) {
                            $statement->bindValue(':traps_id', (int) $traps['traps_id'], PDO::PARAM_INT);
                            $statement->bindValue(':service_id', (int) $maxId, PDO::PARAM_INT);
                            $statement->execute();
                            $fields['service_traps'] .= $traps['traps_id'] . ',';
                        }
                        $fields['service_traps'] = trim($fields['service_traps'], ',');

                        // Extended information duplication
                        $selectEsiStatement = $pearDB->prepare(
                            'SELECT * FROM extended_service_information WHERE service_service_id = :serviceId'
                        );
                        $selectEsiStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectEsiStatement->execute();
                        while ($esi = $selectEsiStatement->fetch()) {
                            $esi['service_service_id'] = $maxId;
                            $esi['esi_id'] = null;
                            $esiColumns = array_keys($esi);
                            $esiPlaceholders = array_map(fn ($col) => ':' . $col, $esiColumns);
                            $esiInsertSql = 'INSERT INTO extended_service_information ('
                                . implode(', ', $esiColumns) . ') VALUES (' . implode(', ', $esiPlaceholders) . ')';
                            $esiInsertStatement = $pearDB->prepare($esiInsertSql);
                            foreach ($esi as $esiCol => $esiVal) {
                                $esiInsertStatement->bindValue(
                                    ':' . $esiCol,
                                    $esiVal,
                                    $esiVal === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                                );
                            }
                            $esiInsertStatement->execute();
                            foreach ($esi as $key2 => $value2) {
                                if ($key2 != 'esi_id') {
                                    $fields[$key2] = $value2;
                                }
                            }
                        }

                        // On demand macros
                        $selectMacroStatement = $pearDB->prepare(
                            'SELECT svc_macro_id, svc_svc_id, svc_macro_name, svc_macro_value, is_password, description, macro_order '
                            . 'FROM `on_demand_macro_service` WHERE `svc_svc_id` = :serviceId'
                        );
                        $selectMacroStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectMacroStatement->execute();
                        $dbResult3 = $selectMacroStatement;
                        $macroPasswords = [];
                        while ($sv = $dbResult3->fetch()) {
                            $macName = str_replace('$', '', $sv['svc_macro_name']);
                            $macVal = $sv['svc_macro_value'];
                            if (! isset($sv['is_password'])) {
                                $sv['is_password'] = '0';
                            }
                            $mTpRq2 = 'INSERT INTO `on_demand_macro_service` (`svc_svc_id`, `svc_macro_name`, '
                                . '`svc_macro_value`, `is_password`)
                                VALUES (:svc_svc_id, :svc_macro_name, :svc_macro_value , :is_password)';
                            $statement = $pearDB->prepare($mTpRq2);
                            $statement->bindValue(':svc_svc_id', $maxId, PDO::PARAM_INT);
                            $statement->bindValue(':svc_macro_name', '$' . $macName . '$');
                            $statement->bindValue(':svc_macro_value', $macVal);
                            $statement->bindValue(':is_password', $sv['is_password']);
                            $statement->execute();
                            $fields['_' . strtoupper($macName) . '_'] = $sv['svc_macro_value'];
                            if ($sv['is_password'] === 1) {
                                $maxIdStatement = $pearDB->query(
                                    'SELECT MAX(svc_macro_id) from on_demand_macro_service WHERE is_password = 1'
                                );
                                $resultMacro = $maxIdStatement->fetch();
                                $macroPasswords[$resultMacro['MAX(svc_macro_id)']] = [
                                    'macroName' => $macName,
                                    'macroValue' => $macVal,
                                ];
                            }
                        }

                        if ($macroPasswords !== [] && $vaultEligibilityService->shouldUseVault()) {
                            /** @var ReadVaultRepositoryInterface $readVaultRepository */
                            $readVaultRepository = $kernel->getContainer()->get(
                                ReadVaultRepositoryInterface::class
                            );
                            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
                            $writeVaultRepository = $kernel->getContainer()->get(
                                WriteVaultRepositoryInterface::class
                            );
                            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
                            try {
                                duplicateServiceSecretsInVault(
                                    $readVaultRepository,
                                    $writeVaultRepository,
                                    $logger,
                                    $key,
                                    $macroPasswords,
                                );
                            } catch (Throwable $ex) {
                                error_log((string) $ex);
                            }
                        }

                        // Service categories
                        $selectScStatement = $pearDB->prepare(
                            'SELECT sc_id FROM `service_categories_relation` WHERE `service_service_id` = :serviceId'
                        );
                        $selectScStatement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
                        $selectScStatement->execute();
                        $dbResult3 = $selectScStatement;
                        $mTpRq2 = 'INSERT INTO `service_categories_relation` (`service_service_id`, `sc_id`) '
                            . 'VALUES (:service_service_id, :sc_id)';
                        $statement = $pearDB->prepare($mTpRq2);
                        while ($sv = $dbResult3->fetch()) {
                            $statement->bindValue(
                                ':service_service_id',
                                (int) $maxId,
                                PDO::PARAM_INT
                            );
                            $statement->bindValue(':sc_id', (int) $sv['sc_id'], PDO::PARAM_INT);
                            $statement->execute();
                        }

                        // get svc desc
                        $query = 'SELECT service_description FROM service '
                            . 'WHERE service_id = :service_id LIMIT 1';
                        $statement = $pearDB->prepare($query);
                        $statement->bindValue(':service_id', (int) $maxId, PDO::PARAM_INT);
                        $statement->execute();
                        if ($statement->rowCount()) {
                            $row2 = $statement->fetch(PDO::FETCH_ASSOC);
                            $description = $row2['service_description'];
                            $centreon->CentreonLogAction->insertLog(
                                object_type: ActionLog::OBJECT_TYPE_SERVICE,
                                object_id: $maxId,
                                object_name: $description,
                                action_type: ActionLog::ACTION_TYPE_ADD,
                                fields: $fields
                            );
                        }

                        signalConfigurationChange('service', (int) $maxId);
                    }
                }
            }
            $centreon->user->access->updateACL(
                ['type' => 'SERVICE', 'id' => $maxId, 'action' => 'DUP', 'duplicate_service' => $key]
            );
        }
    }

    return $maxId;
}

function updateServiceForCloud($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $form, $pearDB, $centreon;

    if (! $serviceId) {
        return;
    }

    $service = new CentreonService($pearDB);

    $ret = [];
    $ret = count($parameters) ? $parameters : $form->getSubmitValues();

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    // Retrieve vault path before updating values in database.
    $vaultPath = null;
    if ($vaultEligibilityService->shouldUseVault()) {
        $vaultPath = retrieveServiceVaultPathFromDatabase($pearDB, $serviceId);
    }

    if (! empty($ret['command_command_id'])) {
        $kernel = Kernel::createForWeb();
        /** @var ReadCommandRepositoryInterface $commandRepository */
        $commandRepository = $kernel->getContainer()->get(ReadCommandRepositoryInterface::class);
        $command = $commandRepository->findById((int) $ret['command_command_id']);
        if ($command === null) {
            throw new InvalidArgumentException('The command ID does not exist.');
        }
        if ($command->isCentreonMonitoringAgentCommand()) {
            $ret['service_check_freshness']['service_check_freshness'] = '1';
            $ret['service_freshness_threshold'] = 120;
        }
    }
    $ret['service_description'] = $service->checkIllegalChar($ret['service_description']);

    $bindParams = [];
    $rq = 'UPDATE service SET ';
    $rq .= 'service_template_model_stm_id = :service_template_model_stm_id, ';
    $bindParams[':service_template_model_stm_id'] = isset($ret['service_template_model_stm_id']) && $ret['service_template_model_stm_id'] != null
        ? (int) $ret['service_template_model_stm_id'] : null;
    $rq .= 'command_command_id = :command_command_id, ';
    $bindParams[':command_command_id'] = isset($ret['command_command_id']) && $ret['command_command_id'] != null
        ? (int) $ret['command_command_id'] : null;
    $rq .= 'timeperiod_tp_id = :timeperiod_tp_id, ';
    $bindParams[':timeperiod_tp_id'] = isset($ret['timeperiod_tp_id']) && $ret['timeperiod_tp_id'] != null
        ? (int) $ret['timeperiod_tp_id'] : null;
    $rq .= 'command_command_id2 = :command_command_id2, ';
    $bindParams[':command_command_id2'] = isset($ret['command_command_id2']) && $ret['command_command_id2'] != null
        ? (int) $ret['command_command_id2'] : null;

    // If we are doing a MC, we don't have to set name and alias field
    if (! $massiveChange) {
        $rq .= 'service_description = :service_description, ';
        $bindParams[':service_description'] = isset($ret['service_description']) && $ret['service_description'] != null
            ? $ret['service_description'] : null;
    }
    $rq .= 'service_alias = :service_alias, ';
    $bindParams[':service_alias'] = isset($ret['service_alias']) && $ret['service_alias'] != null
        ? $ret['service_alias'] : null;
    $rq .= "service_acknowledgement_timeout = null, service_is_volatile = '2', ";
    $rq .= 'service_max_check_attempts = :service_max_check_attempts, ';
    $bindParams[':service_max_check_attempts'] = isset($ret['service_max_check_attempts']) && $ret['service_max_check_attempts'] != null
        ? (int) $ret['service_max_check_attempts'] : null;
    $rq .= 'service_normal_check_interval = :service_normal_check_interval, ';
    $bindParams[':service_normal_check_interval'] = isset($ret['service_normal_check_interval']) && $ret['service_normal_check_interval'] != null
        ? $ret['service_normal_check_interval'] : null;
    $rq .= 'service_retry_check_interval = :service_retry_check_interval, ';
    $bindParams[':service_retry_check_interval'] = isset($ret['service_retry_check_interval']) && $ret['service_retry_check_interval'] != null
        ? $ret['service_retry_check_interval'] : null;
    $rq .= "service_passive_checks_enabled = '2', service_obsess_over_service = '2', ";
    $rq .= 'service_check_freshness = :service_check_freshness, ';
    $bindParams[':service_check_freshness'] = $ret['service_check_freshness']['service_check_freshness'] ?? '2';
    $rq .= 'service_freshness_threshold = :service_freshness_threshold, ';
    $bindParams[':service_freshness_threshold'] = isset($ret['service_freshness_threshold']) && $ret['service_freshness_threshold'] != null
        ? $ret['service_freshness_threshold'] : null;
    $rq .= 'service_event_handler_enabled = :service_event_handler_enabled, ';
    $bindParams[':service_event_handler_enabled'] = isset($ret['service_event_handler_enabled']['service_event_handler_enabled'])
        && $ret['service_event_handler_enabled']['service_event_handler_enabled'] != 2
        ? $ret['service_event_handler_enabled']['service_event_handler_enabled'] : '2';
    $rq .= 'service_low_flap_threshold = null, service_high_flap_threshold = null, ';
    $rq .= "service_flap_detection_enabled = '2', service_retain_status_information = '2', ";
    $rq .= "service_retain_nonstatus_information = '2', service_notifications_enabled = '2', ";
    $rq .= 'service_recovery_notification_delay = null, service_use_only_contacts_from_host = null, ';
    $rq .= 'contact_additive_inheritance = 0, cg_additive_inheritance = 0, ';
    $rq .= 'service_stalking_options = null, service_comment = null, ';
    $rq .= 'geo_coords = :geo_coords, ';
    $bindParams[':geo_coords'] = isset($ret['geo_coords']) && $ret['geo_coords'] != null
        ? $ret['geo_coords'] : null;
    $ret['command_command_id_arg'] = getCommandArgs($_POST, $ret);
    $rq .= 'command_command_id_arg = :command_command_id_arg, ';
    $bindParams[':command_command_id_arg'] = isset($ret['command_command_id_arg']) && $ret['command_command_id_arg'] != null
        ? $ret['command_command_id_arg'] : null;
    $rq .= 'command_command_id_arg2 = null, ';
    $rq .= 'service_register = :service_register, ';
    $bindParams[':service_register'] = isset($ret['service_register']) && $ret['service_register'] != null
        ? $ret['service_register'] : null;
    $rq .= 'service_activate = :service_activate ';
    $bindParams[':service_activate'] = isset($ret['service_activate']['service_activate']) && $ret['service_activate']['service_activate'] != null
        ? $ret['service_activate']['service_activate'] : '1';
    $rq .= 'WHERE service_id = :serviceId';
    $bindParams[':serviceId'] = (int) $serviceId;
    $statement = $pearDB->prepare($rq);
    foreach ($bindParams as $param => $paramValue) {
        $statement->bindValue($param, $paramValue);
    }
    $statement->execute();

    // Update demand macros
    if (isset($_REQUEST['macroInput'], $_REQUEST['macroValue'])) {
        $macroDescription = [];
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $serviceId,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            (! isset($_REQUEST['macroPassword']) ? 0 : $_REQUEST['macroPassword']),
            $macroDescription,
            $massiveChange
        );
    } else {
        $deleteMacroStatement = $pearDB->prepare(
            'DELETE FROM on_demand_macro_service WHERE svc_svc_id = :serviceId'
        );
        $deleteMacroStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $deleteMacroStatement->execute();
    }

    if ($vaultEligibilityService->shouldUseVault()) {
        /** @var ReadVaultRepositoryInterface $readVaultRepository */
        $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

        /** @var WriteVaultRepositoryInterface $writeVaultRepository */
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
        $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
        try {
            updateServiceSecretsInVault(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $vaultPath,
                (int) $serviceId,
                $service->getFormattedMacros(),
            );
        } catch (Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($ret['criticality_id'])) {
        setServiceCriticality($serviceId, $ret['criticality_id']);
    }

    $centreon->user->access->updateACL(['type' => 'SERVICE', 'id' => $serviceId, 'action' => 'UPDATE']);

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICE,
        object_id: $serviceId,
        object_name: $ret['service_description'],
        action_type: ActionLog::ACTION_TYPE_CHANGE,
        fields: $fields
    );
}

function updateService_MCForCloud($serviceId = null, $parameters = [])
{
    if (! $serviceId) {
        return;
    }
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    $ret = [];
    $ret = count($parameters) ? $parameters : $form->getSubmitValues();

    $kernel = Kernel::createForWeb();
    /** @var UUIDGeneratorInterface $uuidGenerator */
    $uuidGenerator = $kernel->getContainer()->get(UUIDGeneratorInterface::class);
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    // Retrieve UUID for vault path before updating values in database.
    $uuid = null;
    if ($vaultEligibilityService->shouldUseVault()) {
        $uuid = retrieveServiceSecretUuidFromDatabase($pearDB, $serviceId);
    }

    if (! empty($ret['command_command_id'])) {
        $commandRepository = $kernel->getContainer()->get(ReadCommandRepositoryInterface::class);
        $command = $commandRepository->findById((int) $ret['command_command_id']);
        if ($command === null) {
            throw new InvalidArgumentException('The command ID does not exist.');
        }
        if ($command->isCentreonMonitoringAgentCommand()) {
            $ret['service_check_freshness']['service_check_freshness'] = '1';
            $ret['service_freshness_threshold'] = 120;
        }
    }

    if (isset($ret['sg_name'])) {
        $ret['sg_name'] = $centreon->checkIllegalChar($ret['sg_name']);
    }

    if (isset($ret['command_command_id_arg']) && $ret['command_command_id_arg'] != null) {
        $ret['command_command_id_arg'] = str_replace("\n", '//BR//', $ret['command_command_id_arg']);
        $ret['command_command_id_arg'] = str_replace("\t", '//T//', $ret['command_command_id_arg']);
        $ret['command_command_id_arg'] = str_replace("\r", '//R//', $ret['command_command_id_arg']);
    }

    $bindParams = [];
    $rq = 'UPDATE service SET ';
    if (isset($ret['service_template_model_stm_id']) && $ret['service_template_model_stm_id'] != null) {
        $rq .= 'service_template_model_stm_id = :service_template_model_stm_id, ';
        $bindParams[':service_template_model_stm_id'] = (int) $ret['service_template_model_stm_id'];
    }
    if (isset($ret['command_command_id']) && $ret['command_command_id'] != null) {
        $rq .= 'command_command_id = :command_command_id, ';
        $bindParams[':command_command_id'] = (int) $ret['command_command_id'];
    }
    if (isset($ret['timeperiod_tp_id']) && $ret['timeperiod_tp_id'] != null) {
        $rq .= 'timeperiod_tp_id = :timeperiod_tp_id, ';
        $bindParams[':timeperiod_tp_id'] = (int) $ret['timeperiod_tp_id'];
    }

    $rq .= 'command_command_id2 = :command_command_id2, ';
    $bindParams[':command_command_id2'] = isset($ret['command_command_id2']) && $ret['command_command_id2'] != null
        ? (int) $ret['command_command_id2'] : null;

    if (isset($ret['service_alias']) && $ret['service_alias'] != null) {
        $rq .= 'service_alias = :service_alias, ';
        $bindParams[':service_alias'] = $ret['service_alias'];
    }

    if (isset($ret['service_max_check_attempts']) && $ret['service_max_check_attempts'] != null) {
        $rq .= 'service_max_check_attempts = :service_max_check_attempts, ';
        $bindParams[':service_max_check_attempts'] = (int) $ret['service_max_check_attempts'];
    }

    if (isset($ret['service_normal_check_interval']) && $ret['service_normal_check_interval'] != null) {
        $rq .= 'service_normal_check_interval = :service_normal_check_interval, ';
        $bindParams[':service_normal_check_interval'] = $ret['service_normal_check_interval'];
    }
    if (isset($ret['service_retry_check_interval']) && $ret['service_retry_check_interval'] != null) {
        $rq .= 'service_retry_check_interval = :service_retry_check_interval, ';
        $bindParams[':service_retry_check_interval'] = $ret['service_retry_check_interval'];
    }

    $rq .= "service_acknowledgement_timeout = null, service_is_volatile = '2', ";
    $rq .= "service_active_checks_enabled = '2', service_passive_checks_enabled = '2', ";
    $rq .= "service_obsess_over_service = '2', ";
    $rq .= 'service_check_freshness = :service_check_freshness, ';
    $bindParams[':service_check_freshness'] = $ret['service_check_freshness']['service_check_freshness'] ?? '2';
    $rq .= 'service_freshness_threshold = :service_freshness_threshold, ';
    $bindParams[':service_freshness_threshold'] = isset($ret['service_freshness_threshold']) && $ret['service_freshness_threshold'] != null
        ? $ret['service_freshness_threshold'] : null;

    $rq .= 'service_event_handler_enabled = :service_event_handler_enabled, ';
    $bindParams[':service_event_handler_enabled'] = isset($ret['service_event_handler_enabled']['service_event_handler_enabled'])
        && $ret['service_event_handler_enabled']['service_event_handler_enabled'] != 2
        ? $ret['service_event_handler_enabled']['service_event_handler_enabled'] : '2';

    $rq .= 'service_low_flap_threshold = null, service_high_flap_threshold = null, ';
    $rq .= "service_flap_detection_enabled = '2', service_retain_status_information = '2', ";
    $rq .= "service_retain_nonstatus_information = '2', ";
    $rq .= "service_notifications_enabled = '2', service_recovery_notification_delay = null, ";
    $rq .= 'cg_additive_inheritance = 0, service_use_only_contacts_from_host = null, ';
    $rq .= 'service_stalking_options = null, service_comment = null, ';

    $ret['command_command_id_arg'] = getCommandArgs($_POST, $ret);
    $rq .= 'command_command_id_arg = :command_command_id_arg, ';
    $bindParams[':command_command_id_arg'] = isset($ret['command_command_id_arg']) && $ret['command_command_id_arg'] != null
        ? $ret['command_command_id_arg'] : null;
    $rq .= 'command_command_id_arg2 = null, ';
    if (isset($ret['service_register']) && $ret['service_register'] != null) {
        $rq .= 'service_register = :service_register, ';
        $bindParams[':service_register'] = $ret['service_register'];
    }
    if (isset($ret['geo_coords']) && $ret['geo_coords'] != null) {
        $rq .= 'geo_coords = :geo_coords, ';
        $bindParams[':geo_coords'] = $ret['geo_coords'];
    }
    if (isset($ret['service_activate']['service_activate']) && $ret['service_activate']['service_activate'] != null) {
        $rq .= 'service_activate = :service_activate, ';
        $bindParams[':service_activate'] = $ret['service_activate']['service_activate'];
    }

    if (strcmp('UPDATE service SET ', $rq)) {
        // Delete last ',' in request
        $rq[strlen($rq) - 2] = ' ';
        $rq .= 'WHERE service_id = :serviceId';
        $bindParams[':serviceId'] = (int) $serviceId;
        $statement = $pearDB->prepare($rq);
        foreach ($bindParams as $param => $paramValue) {
            $statement->bindValue($param, $paramValue);
        }
        $statement->execute();
    }

    // Update on demand macros
    $macroDescription = [];
    foreach ($_REQUEST as $nam => $ele) {
        if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $macroDescription[$match[1]] = $ele;
            }
        }
    }
    if (isset($_REQUEST['macroInput'], $_REQUEST['macroValue'])) {
        $service->insertMacro(
            $serviceId,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            $_REQUEST['macroPassword'] ?? [],
            $macroDescription,
            true,
            false,
            $_REQUEST['macroFrom']
        );
    }
    if (isset($ret['criticality_id']) && $ret['criticality_id']) {
        setServiceCriticality($serviceId, $ret['criticality_id']);
    }

    // If there is a vault configuration write into vault
    if ($vaultEligibilityService->shouldUseVault()) {
        try {
            /** @var ReadVaultRepositoryInterface $readVaultRepository */
            $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);

            $updatedPasswordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
                return $macro['macroPassword'] === '1'
                    && ! str_starts_with($macro['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN);
            });
            updateServiceSecretsInVaultFromMC(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $uuid,
                (int) $serviceId,
                $updatedPasswordMacros
            );
        } catch (Throwable $ex) {
            error_log((string) $ex);
        }
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICE,
        object_id: $serviceId,
        object_name: $ret['service_description'] ?? '',
        action_type: ActionLog::ACTION_TYPE_MASS_CHANGE,
        fields: $fields
    );
}

function updateServiceHostForCloud($serviceId = null, $submittedValues = [], $isMassiveChange = false)
{
    global $form, $pearDB;

    if (! $serviceId) {
        return;
    }

    $ret1 = [];
    $ret2 = [];
    if (isset($submittedValues['service_hPars'])) {
        $ret1 = $submittedValues['service_hPars'];
    } else {
        $ret1 = CentreonUtils::mergeWithInitialValues($form, 'service_hPars');
    }
    if (isset($submittedValues['service_hgPars'])) {
        $ret2 = $submittedValues['service_hgPars'];
    } else {
        $ret2 = CentreonUtils::mergeWithInitialValues($form, 'service_hgPars');
    }

    // Get actual config
    $rq = 'SELECT host_host_id FROM escalation_service_relation '
        . ' WHERE service_service_id = :service_id';
    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
    $statement->execute();
    $cacheEsc = [];
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cacheEsc[$data['host_host_id']] = 1;
    }

    // Get actual config
    $rq = 'SELECT host_host_id FROM host_service_relation '
        . ' WHERE service_service_id = :service_id ';
    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
    $statement->execute();
    $cache = [];
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cache[$data['host_host_id']] = 1;
    }

    if (count($ret1) == 1) {
        foreach ($cache as $host_id => $flag) {
            if (! isset($cacheEsc[$host_id]) && count($cacheEsc)) {
                $query = 'UPDATE escalation_service_relation
                          SET host_host_id = :host_host_id
                          WHERE service_service_id = :service_id';
                $statement = $pearDB->prepare($query);
                $statement->bindValue(':host_host_id', (int) $ret1[0], PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
                $statement->execute();
            }
        }
    } else {
        foreach ($cache as $host_id) {
            if (! isset($cache[$host_id]) && count($cacheEsc)) {
                $query = 'DELETE FROM escalation_service_relation
                    WHERE host_host_id = :host_host_id AND service_service_id = :service_id';
                $statement = $pearDB->prepare($query);
                $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
                $statement->bindValue(':host_host_id', (int) $ret1[0], PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    if (! $isMassiveChange) {
        $rq = 'DELETE FROM host_service_relation '
            . 'WHERE service_service_id = :service_id ';
        $statement = $pearDB->prepare($rq);
        $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
        $statement->execute();
    } else {
        // Purge service to host relations
        if (count($ret1)) {
            $rq = 'DELETE FROM host_service_relation '
                . 'WHERE service_service_id = :service_id '
                . 'AND host_host_id IS NOT NULL ';
            $statement = $pearDB->prepare($rq);
            $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
            $statement->execute();
        }
        // Purge service to hostgroup relations
        if (count($ret2)) {
            $rq = 'DELETE FROM host_service_relation '
                . 'WHERE service_service_id = :service_id '
                . 'AND hostgroup_hg_id IS NOT NULL ';
            $statement = $pearDB->prepare($rq);
            $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    if (count($ret2)) {
        $insertHgStatement = $pearDB->prepare(
            'INSERT INTO host_service_relation (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) '
            . 'VALUES (:hgId, NULL, NULL, :serviceId)'
        );
        foreach ($ret2 as $hgId) {
            $insertHgStatement->bindValue(':hgId', (int) $hgId, PDO::PARAM_INT);
            $insertHgStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
            $insertHgStatement->execute();
            setHostChangeFlag($pearDB, null, $hgId);
        }
    } elseif (count($ret1)) {
        $insertHostStatement = $pearDB->prepare(
            'INSERT INTO host_service_relation (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) '
            . 'VALUES (NULL, :hostId, NULL, :serviceId)'
        );
        foreach ($ret1 as $hostId) {
            $insertHostStatement->bindValue(':hostId', (int) $hostId, PDO::PARAM_INT);
            $insertHostStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
            $insertHostStatement->execute();
            setHostChangeFlag($pearDB, $hostId, null);
        }
    }
}

function updateServiceHost_MCForCloud($serviceId = null)
{
    global $form, $pearDB;

    if (! $serviceId) {
        return;
    }

    $selectStatement = $pearDB->prepare(
        'SELECT host_host_id, hostgroup_hg_id FROM host_service_relation WHERE service_service_id = :serviceId'
    );
    $selectStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
    $selectStatement->execute();
    $hsvs = [];
    $hgsvs = [];
    while ($arr = $selectStatement->fetch()) {
        if ($arr['host_host_id']) {
            $hsvs[$arr['host_host_id']] = $arr['host_host_id'];
        }
        if ($arr['hostgroup_hg_id']) {
            $hgsvs[$arr['hostgroup_hg_id']] = $arr['hostgroup_hg_id'];
        }
    }
    $ret1 = [];
    $ret2 = [];
    $ret1 = $form->getSubmitValue('service_hPars');
    $ret2 = $form->getSubmitValue('service_hgPars');
    if (is_array($ret2)) {
        $deleteHostStatement = $pearDB->prepare(
            'DELETE FROM host_service_relation WHERE service_service_id = :serviceId AND host_host_id IS NOT NULL'
        );
        $insertHgStatement = $pearDB->prepare(
            'INSERT INTO host_service_relation (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) '
            . 'VALUES (:hgId, NULL, NULL, :serviceId)'
        );
        foreach ($ret2 as $hgId) {
            if (! isset($hgsvs[$hgId])) {
                $deleteHostStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
                $deleteHostStatement->execute();
                $insertHgStatement->bindValue(':hgId', (int) $hgId, PDO::PARAM_INT);
                $insertHgStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
                $insertHgStatement->execute();
                setHostChangeFlag($pearDB, null, $hgId);
            }
        }
    } elseif (is_array($ret1)) {
        $deleteHgStatement = $pearDB->prepare(
            'DELETE FROM host_service_relation WHERE service_service_id = :serviceId AND hostgroup_hg_id IS NOT NULL'
        );
        $insertHostStatement = $pearDB->prepare(
            'INSERT INTO host_service_relation (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) '
            . 'VALUES (NULL, :hostId, NULL, :serviceId)'
        );
        foreach ($ret1 as $hostId) {
            if (! isset($hsvs[$hostId])) {
                $deleteHgStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
                $deleteHgStatement->execute();
                $insertHostStatement->bindValue(':hostId', (int) $hostId, PDO::PARAM_INT);
                $insertHostStatement->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
                $insertHostStatement->execute();
                setHostChangeFlag($pearDB, $hostId, null);
            }
        }
    }
}

function updateServiceInDB($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $isCloudPlatform;

    $isCloudPlatform
        ? updateServiceInDBForCloud($serviceId, $massiveChange, $parameters)
        : updateServiceInDBForOnPrem($serviceId, $massiveChange, $parameters);
}

function updateServiceInDBForCloud($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $form;

    if (! $serviceId) {
        return;
    }

    $ret = count($parameters) ? $parameters : $form->getSubmitValues();

    $isServiceTemplate = isset($ret['service_register']) && $ret['service_register'] === '0';

    $previousPollerIds = getPollersForConfigChangeFlagFromServiceId($serviceId);

    if ($massiveChange) {
        updateService_MCForCloud($serviceId);
    } else {
        updateServiceForCloud($serviceId, $massiveChange, $parameters);
    }

    // Function for updating host/hg parent
    if ($massiveChange) {
        updateServiceHost_MCForCloud($serviceId);
    } else {
        updateServiceHostForCloud($serviceId, $parameters);
    }

    if (! $isServiceTemplate) {
        if ($massiveChange) {
            updateServiceServiceGroup_MC($serviceId);
        } else {
            updateServiceServiceGroup($serviceId);
        }
    }

    if ($massiveChange) {
        updateServiceExtInfos_MC($serviceId);
    } else {
        updateServiceExtInfos($serviceId);
    }

    if ($massiveChange) {
        updateServiceCategories_MC($serviceId);
    } else {
        updateServiceCategories($serviceId);
    }

    signalConfigurationChange('service', $serviceId, $previousPollerIds);
}

function updateServiceInDBForOnPrem($serviceId = null, $massiveChange = false, $parameters = [])
{
    global $form;

    if (! count($parameters)) {
        $parameters = $form->getSubmitValues();
    }

    if (! $serviceId) {
        return;
    }

    $ret = count($parameters) ? $parameters : $form->getSubmitValues();

    $isServiceTemplate = isset($ret['service_register']) && $ret['service_register'] === '0';

    $previousPollerIds = getPollersForConfigChangeFlagFromServiceId($serviceId);

    if ($massiveChange) {
        updateService_MC($serviceId);
    } else {
        updateService($serviceId, $massiveChange, $parameters);
    }
    // Function for updating cg
    // 1 - MC with deletion of existing cg
    // 2 - MC with addition of new cg
    // 3 - Normal update
    if (isset($ret['mc_mod_cgs']['mc_mod_cgs']) && $ret['mc_mod_cgs']['mc_mod_cgs']) {
        updateServiceContactGroup($serviceId, $parameters);
        updateServiceContact($serviceId, $parameters);
    } elseif (isset($ret['mc_mod_cgs']['mc_mod_cgs']) && ! $ret['mc_mod_cgs']['mc_mod_cgs']) {
        updateServiceContactGroup_MC($serviceId);
        updateServiceContact_MC($serviceId);
    } else {
        updateServiceContactGroup($serviceId, $parameters);
        updateServiceContact($serviceId, $parameters);
    }

    // Function for updating notification options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update
    if (isset($ret['mc_mod_notifopts']['mc_mod_notifopts']) && $ret['mc_mod_notifopts']['mc_mod_notifopts']) {
        updateServiceNotifs($serviceId);
    } elseif (isset($ret['mc_mod_notifopts']['mc_mod_notifopts']) && ! $ret['mc_mod_notifopts']['mc_mod_notifopts']) {
        updateServiceNotifs_MC($serviceId);
    } else {
        updateServiceNotifs($serviceId);
    }

    // Function for updating notification interval options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update
    if (
        isset($ret['mc_mod_notifopt_notification_interval']['mc_mod_notifopt_notification_interval'])
        && $ret['mc_mod_notifopt_notification_interval']['mc_mod_notifopt_notification_interval']
    ) {
        updateServiceNotifOptionInterval($serviceId);
    } elseif (
        isset($ret['mc_mod_notifopt_notification_interval']['mc_mod_notifopt_notification_interval'])
        && ! $ret['mc_mod_notifopt_notification_interval']['mc_mod_notifopt_notification_interval']
    ) {
        updateServiceNotifOptionInterval_MC($serviceId);
    } else {
        updateServiceNotifOptionInterval($serviceId);
    }

    // Function for updating first notification delay options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update, default behavior
    if (
        isset($ret['mc_mod_notifopt_first_notification_delay']['mc_mod_notifopt_first_notification_delay'])
        && $ret['mc_mod_notifopt_first_notification_delay']['mc_mod_notifopt_first_notification_delay']
    ) {
        updateServiceNotifOptionFirstNotificationDelay($serviceId);
    } elseif (
        isset($ret['mc_mod_notifopt_first_notification_delay']['mc_mod_notifopt_first_notification_delay'])
        && ! $ret['mc_mod_notifopt_first_notification_delay']['mc_mod_notifopt_first_notification_delay']
    ) {
        updateServiceNotifOptionFirstNotificationDelay_MC($serviceId);
    } else {
        updateServiceNotifOptionFirstNotificationDelay($serviceId);
    }

    // Function for updating notification timeperiod options
    // 1 - MC with deletion of existing options (Replacement)
    // 2 - MC with addition of new options (incremental)
    // 3 - Normal update
    if (
        isset($ret['mc_mod_notifopt_timeperiod']['mc_mod_notifopt_timeperiod'])
        && $ret['mc_mod_notifopt_timeperiod']['mc_mod_notifopt_timeperiod']
    ) {
        updateServiceNotifOptionTimeperiod($serviceId, $parameters);
    } elseif (
        isset($ret['mc_mod_notifopt_timeperiod']['mc_mod_notifopt_timeperiod'])
        && ! $ret['mc_mod_notifopt_timeperiod']['mc_mod_notifopt_timeperiod']
    ) {
        updateServiceNotifOptionTimeperiod_MC($serviceId);
    } else {
        updateServiceNotifOptionTimeperiod($serviceId, $parameters);
    }

    // Function for updating host/hg parent
    // 1 - MC with deletion of existing host/hg parent
    // 2 - MC with addition of new host/hg parent
    // 3 - Normal update
    if (isset($ret['mc_mod_Pars']['mc_mod_Pars']) && $ret['mc_mod_Pars']['mc_mod_Pars']) {
        updateServiceHost($serviceId, $parameters, true);
    } elseif (isset($ret['mc_mod_Pars']['mc_mod_Pars']) && ! $ret['mc_mod_Pars']['mc_mod_Pars']) {
        updateServiceHost_MC($serviceId);
    } else {
        updateServiceHost($serviceId, $parameters);
    }

    // Function for updating sg
    // 1 - MC with deletion of existing sg
    // 2 - MC with addition of new sg
    // 3 - Normal update
    if (! $isServiceTemplate) {
        if (isset($ret['mc_mod_sgs']['mc_mod_sgs']) && $ret['mc_mod_sgs']['mc_mod_sgs']) {
            updateServiceServiceGroup($serviceId);
        } elseif (isset($ret['mc_mod_sgs']['mc_mod_sgs']) && ! $ret['mc_mod_sgs']['mc_mod_sgs']) {
            updateServiceServiceGroup_MC($serviceId);
        } else {
            updateServiceServiceGroup($serviceId);
        }
    }

    if ($massiveChange) {
        updateServiceExtInfos_MC($serviceId);
    } else {
        updateServiceExtInfos($serviceId);
    }
    // Function for updating traps
    // 1 - MC with deletion of existing traps
    // 2 - MC with addition of new traps
    // 3 - Normal update
    if (isset($ret['mc_mod_traps']['mc_mod_traps']) && $ret['mc_mod_traps']['mc_mod_traps']) {
        updateServiceTrap($serviceId);
    } elseif (isset($ret['mc_mod_traps']['mc_mod_traps']) && ! $ret['mc_mod_traps']['mc_mod_traps']) {
        updateServiceTrap_MC($serviceId);
    } else {
        updateServiceTrap($serviceId);
    }
    // Function for updating categories
    // 1 - MC with deletion of existing categories
    // 2 - MC with addition of new categories
    // 3 - Normal update
    if (isset($ret['mc_mod_sc']['mc_mod_sc']) && $ret['mc_mod_sc']['mc_mod_sc']) {
        updateServiceCategories($serviceId);
    } elseif (isset($ret['mc_mod_sc']['mc_mod_sc']) && ! $ret['mc_mod_sc']['mc_mod_sc']) {
        updateServiceCategories_MC($serviceId);
    } else {
        updateServiceCategories($serviceId);
    }

    signalConfigurationChange('service', $serviceId, $previousPollerIds);
}

/**
 * Insert additional options for service template
 *
 * @param int $serviceId
 * @param array $submittedValues
 *
 * @throws CentreonDbException
 */
function insertServiceTemplateAdditionalOptions(int $serviceId, array $submittedValues): void
{
    global $pearDB;

    try {
        $statement = $pearDB->prepareQuery(
            <<<'SQL'
                    UPDATE service SET
                        service_use_only_contacts_from_host = :use_only_contacts_from_host,
                        service_stalking_options = :stalking_options,
                        service_obsess_over_service = :obsess_over_service,
                        service_retain_nonstatus_information = :retain_nonstatus_information,
                        service_retain_status_information = :retain_status_information
                    WHERE service_id = :service_id
                SQL
        );

        $use_only_contacts_from_host
            = isset($submittedValues['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'])
            && $submittedValues['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'] != null
                ? $submittedValues['service_use_only_contacts_from_host']['service_use_only_contacts_from_host']
                : null;
        $stalking_options = isset($submittedValues['service_stalOpts'])
            ? implode(',', array_keys($submittedValues['service_stalOpts']))
            : null;
        $obsess_over_service = isset($submittedValues['service_obsess_over_service']['service_obsess_over_service'])
            && $submittedValues['service_obsess_over_service']['service_obsess_over_service'] != 2
                ? $submittedValues['service_obsess_over_service']['service_obsess_over_service']
                : '2';
        $retain_nonstatus_information
            = isset($submittedValues['service_retain_nonstatus_information']['service_retain_nonstatus_information'])
            && $submittedValues['service_retain_nonstatus_information']['service_retain_nonstatus_information'] != 2
                ? $submittedValues['service_retain_nonstatus_information']['service_retain_nonstatus_information']
                : '2';
        $retain_status_information
            = isset($submittedValues['service_retain_status_information']['service_retain_status_information'])
            && $submittedValues['service_retain_status_information']['service_retain_status_information'] != 2
                ? $submittedValues['service_retain_status_information']['service_retain_status_information']
                : '2';

        $bindParams = [
            ':use_only_contacts_from_host' => [$use_only_contacts_from_host, PDO::PARAM_STR],
            ':stalking_options' => [$stalking_options, PDO::PARAM_STR],
            ':obsess_over_service' => [$obsess_over_service, PDO::PARAM_STR],
            ':retain_nonstatus_information' => [$retain_nonstatus_information, PDO::PARAM_STR],
            ':retain_status_information' => [$retain_status_information, PDO::PARAM_STR],
            ':service_id' => [$serviceId, PDO::PARAM_INT],
        ];

        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (PDOException $exception) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while creating service template: {$exception->getMessage()}",
            customContext: ['service template Id' => $serviceId],
            exception: $exception
        );

        throw $exception;
    }
}

function insertServiceInDB($submittedValues = [], $onDemandMacro = null)
{
    global $isCloudPlatform;

    return $isCloudPlatform
        ? insertServiceInDBForCloud($submittedValues, $onDemandMacro)
        : insertServiceInDBForOnPremise($submittedValues, $onDemandMacro);
}

function insertServiceInDBForCloud($submittedValues = [], $onDemandMacro = null)
{
    global $centreon;

    $tmp_fields = insertServiceForCloud($submittedValues, $onDemandMacro);
    if (! isset($tmp_fields['service_id'])) {
        return null;
    }

    $serviceId = (int) $tmp_fields['service_id'];
    updateServiceHost($serviceId, $submittedValues);
    updateServiceServiceGroup($serviceId, $submittedValues);
    insertServiceExtInfos($serviceId, $submittedValues);
    updateServiceCategories($serviceId, $submittedValues);

    signalConfigurationChange('service', $serviceId);
    $centreon->user->access->updateACL(
        [
            'type' => 'SERVICE',
            'id' => $serviceId,
            'action' => 'ADD',
        ]
    );

    return $serviceId;
}

function insertServiceInDBForOnPremise($submittedValues = [], $onDemandMacro = null)
{
    global $form, $centreon;

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }
    $tmp_fields = insertServiceForOnPremise($submittedValues, $onDemandMacro);
    if (! isset($tmp_fields['service_id'])) {
        return null;
    }

    $serviceId = (int) $tmp_fields['service_id'];
    updateServiceContactGroup($serviceId, $submittedValues);
    updateServiceContact($serviceId, $submittedValues);
    updateServiceNotifs($serviceId, $submittedValues);
    updateServiceNotifOptionInterval($serviceId, $submittedValues);
    updateServiceNotifOptionTimeperiod($serviceId, $submittedValues);
    updateServiceNotifOptionFirstNotificationDelay($serviceId, $submittedValues);
    updateServiceHost($serviceId, $submittedValues);
    updateServiceServiceGroup($serviceId, $submittedValues);
    insertServiceExtInfos($serviceId, $submittedValues);
    updateServiceTrap($serviceId, $submittedValues);
    updateServiceCategories($serviceId, $submittedValues);

    signalConfigurationChange('service', $serviceId);
    $centreon->user->access->updateACL(
        [
            'type' => 'SERVICE',
            'id' => $serviceId,
            'action' => 'ADD',
        ]
    );

    return $serviceId;
}

function insertServiceForCloud($submittedValues = [], $onDemandMacro = null)
{
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }

    if (! empty($submittedValues['command_command_id'])) {
        $kernel = Kernel::createForWeb();
        /** @var ReadCommandRepositoryInterface $commandRepository */
        $commandRepository = $kernel->getContainer()->get(ReadCommandRepositoryInterface::class);
        $command = $commandRepository->findById((int) $submittedValues['command_command_id']);
        if ($command === null) {
            throw new InvalidArgumentException('The command ID does not exist.');
        }
        if ($command->isCentreonMonitoringAgentCommand()) {
            $submittedValues['service_check_freshness']['service_check_freshness'] = '1';
            $submittedValues['service_freshness_threshold'] = 120;
        }
    }

    $submittedValues['service_description'] = $service->checkIllegalChar($submittedValues['service_description']);
    $find = '/\s{2,}/';
    $submittedValues['service_description'] = preg_replace($find, ' ', $submittedValues['service_description']);

    $submittedValues['command_command_id_arg'] = getCommandArgs($_POST, $submittedValues);
    $bindParams = [];
    $request = 'INSERT INTO service '
        . '(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, '
        . 'timeperiod_tp_id2, service_description, service_alias, service_is_volatile, service_max_check_attempts, '
        . 'service_normal_check_interval, service_retry_check_interval, service_active_checks_enabled, '
        . 'service_passive_checks_enabled, service_obsess_over_service, service_check_freshness, '
        . 'service_freshness_threshold, service_event_handler_enabled, service_low_flap_threshold, '
        . 'service_high_flap_threshold, service_flap_detection_enabled, service_retain_status_information, '
        . 'service_retain_nonstatus_information, service_notification_interval, service_notification_options, '
        . 'service_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, '
        . 'service_use_only_contacts_from_host, service_stalking_options, '
        . 'service_first_notification_delay, service_recovery_notification_delay,'
        . 'service_comment, geo_coords, command_command_id_arg, command_command_id_arg2, '
        . 'service_register, service_activate, service_acknowledgement_timeout) '
        . 'VALUES ('
        . ':service_template_model_stm_id, :command_command_id, :timeperiod_tp_id, :command_command_id2, '
        . "null, :service_description, :service_alias, '2', :service_max_check_attempts, "
        . ":service_normal_check_interval, :service_retry_check_interval, '2', '2', '2', "
        . ':service_check_freshness, :service_freshness_threshold, :service_event_handler_enabled, '
        . "null, null, '2', '2', '2', null, null, '2', 0, 0, null, null, null, null, null, "
        . ':geo_coords, :command_command_id_arg, null, :service_register, :service_activate, NULL)';
    $bindParams[':service_template_model_stm_id'] = isset($submittedValues['service_template_model_stm_id']) && $submittedValues['service_template_model_stm_id'] != null
        ? (int) $submittedValues['service_template_model_stm_id'] : null;
    $bindParams[':command_command_id'] = isset($submittedValues['command_command_id']) && $submittedValues['command_command_id'] != null
        ? (int) $submittedValues['command_command_id'] : null;
    $bindParams[':timeperiod_tp_id'] = isset($submittedValues['timeperiod_tp_id']) && $submittedValues['timeperiod_tp_id'] != null
        ? (int) $submittedValues['timeperiod_tp_id'] : null;
    $bindParams[':command_command_id2'] = isset($submittedValues['command_command_id2']) && $submittedValues['command_command_id2'] != null
        ? (int) $submittedValues['command_command_id2'] : null;
    $bindParams[':service_description'] = isset($submittedValues['service_description']) && $submittedValues['service_description'] != null
        ? $submittedValues['service_description'] : null;
    $bindParams[':service_alias'] = isset($submittedValues['service_alias']) && $submittedValues['service_alias'] != null
        ? $submittedValues['service_alias'] : null;
    $bindParams[':service_max_check_attempts'] = isset($submittedValues['service_max_check_attempts']) && $submittedValues['service_max_check_attempts'] != null
        ? (int) $submittedValues['service_max_check_attempts'] : null;
    $bindParams[':service_normal_check_interval'] = isset($submittedValues['service_normal_check_interval']) && $submittedValues['service_normal_check_interval'] != null
        ? $submittedValues['service_normal_check_interval'] : null;
    $bindParams[':service_retry_check_interval'] = isset($submittedValues['service_retry_check_interval']) && $submittedValues['service_retry_check_interval'] != null
        ? $submittedValues['service_retry_check_interval'] : null;
    $bindParams[':service_check_freshness'] = $submittedValues['service_check_freshness']['service_check_freshness'] ?? '2';
    $bindParams[':service_freshness_threshold'] = isset($submittedValues['service_freshness_threshold']) && $submittedValues['service_freshness_threshold'] != null
        ? $submittedValues['service_freshness_threshold'] : null;
    $bindParams[':service_event_handler_enabled'] = isset($submittedValues['service_event_handler_enabled']['service_event_handler_enabled'])
        && $submittedValues['service_event_handler_enabled']['service_event_handler_enabled'] != 2
        ? $submittedValues['service_event_handler_enabled']['service_event_handler_enabled'] : '2';
    $bindParams[':geo_coords'] = isset($submittedValues['geo_coords']) && $submittedValues['geo_coords'] != null
        ? $submittedValues['geo_coords'] : null;
    $bindParams[':command_command_id_arg'] = isset($submittedValues['command_command_id_arg']) && $submittedValues['command_command_id_arg'] != null
        ? $submittedValues['command_command_id_arg'] : null;
    $bindParams[':service_register'] = isset($submittedValues['service_register']) && $submittedValues['service_register'] != null
        ? $submittedValues['service_register'] : null;
    $bindParams[':service_activate'] = isset($submittedValues['service_activate']['service_activate']) && $submittedValues['service_activate']['service_activate'] != null
        ? $submittedValues['service_activate']['service_activate'] : '1';
    $statement = $pearDB->prepare($request);
    foreach ($bindParams as $param => $paramValue) {
        $statement->bindValue($param, $paramValue);
    }
    $statement->execute();
    $service_id = (int) $pearDB->lastInsertId();

    // Insert on demand macros
    if (isset($onDemandMacro)) {
        $my_tab = $onDemandMacro;
        if (isset($my_tab['nbOfMacro'])) {
            $already_stored = [];
            for ($i = 0; $i <= $my_tab['nbOfMacro']; $i++) {
                $macInput = 'macroInput_' . $i;
                $macValue = 'macroValue_' . $i;
                if (
                    isset($my_tab[$macInput])
                    && ! isset($already_stored[strtolower($my_tab[$macInput])]) && $my_tab[$macInput]
                ) {
                    $my_tab[$macInput] = str_replace('$_SERVICE', '', $my_tab[$macInput]);
                    $my_tab[$macInput] = str_replace('$', '', $my_tab[$macInput]);
                    $macName = $my_tab[$macInput];
                    $macVal = $my_tab[$macValue];
                    $request = 'INSERT INTO on_demand_macro_service (`svc_macro_name`, `svc_macro_value`, `svc_svc_id`, '
                        . '`macro_order` ) VALUES (:svc_macro_name, :svc_macro_value, :svc_svc_id, :macro_order)';
                    $statement = $pearDB->prepare($request);
                    $statement->bindValue(':svc_macro_name', '$_SERVICE' . strtoupper($macName) . '$', PDO::PARAM_STR);
                    $statement->bindValue(':svc_macro_value', $macVal, PDO::PARAM_STR);
                    $statement->bindValue(':svc_svc_id', (int) $service_id, PDO::PARAM_INT);
                    $statement->bindValue(':macro_order', $i, PDO::PARAM_INT);
                    $statement->execute();
                    $fields['_' . strtoupper($my_tab[$macInput]) . '_'] = $my_tab[$macValue];
                    $already_stored[strtolower($my_tab[$macInput])] = 1;
                }
            }
        }
    } elseif (isset($_REQUEST['macroInput'], $_REQUEST['macroValue'])) {
        $macroDescription = [];
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $service_id,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            $_REQUEST['macroPassword'] ?? null,
            $macroDescription,
            false
        );
    }

    $passwordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
        return $macro['macro_password'] === '1';
    });
    $kernel = Kernel::createForWeb();
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    // If there is a vault configuration  and macros write into vault
    if ($vaultEligibilityService->shouldUseVault() && $passwordMacros !== []) {
        try {
            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
            insertServiceSecretsInVault($writeVaultRepository, $passwordMacros);
        } catch (Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($submittedValues['criticality_id'])) {
        setServiceCriticality($service_id, $submittedValues['criticality_id']);
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICE,
        object_id: $service_id,
        object_name: $submittedValues['service_description'],
        action_type: ActionLog::ACTION_TYPE_ADD,
        fields: $fields
    );

    return ['service_id' => $service_id, 'fields' => $fields];
}

function insertServiceForOnPremise($submittedValues = [], $onDemandMacro = null)
{
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }

    if (! empty($submittedValues['command_command_id'])) {
        $kernel = Kernel::createForWeb();
        /** @var ReadCommandRepositoryInterface $commandRepository */
        $commandRepository = $kernel->getContainer()->get(ReadCommandRepositoryInterface::class);
        $command = $commandRepository->findById((int) $submittedValues['command_command_id']);
        if ($command === null) {
            throw new InvalidArgumentException('The command ID does not exist.');
        }
        if ($command->isCentreonMonitoringAgentCommand()) {
            $submittedValues['service_check_freshness']['service_check_freshness'] = '1';
            $submittedValues['service_freshness_threshold'] = 120;
        }
    }

    $submittedValues['service_description'] = $service->checkIllegalChar($submittedValues['service_description']);
    $find = '/\s{2,}/';
    $submittedValues['service_description'] = preg_replace($find, ' ', $submittedValues['service_description']);

    if (isset($submittedValues['command_command_id_arg2']) && $submittedValues['command_command_id_arg2'] != null) {
        $submittedValues['command_command_id_arg2'] = str_replace("\n", '//BR//', $submittedValues['command_command_id_arg2']);
        $submittedValues['command_command_id_arg2'] = str_replace("\t", '//T//', $submittedValues['command_command_id_arg2']);
        $submittedValues['command_command_id_arg2'] = str_replace("\r", '//R//', $submittedValues['command_command_id_arg2']);
    }
    $submittedValues['command_command_id_arg'] = getCommandArgs($_POST, $submittedValues);
    $bindParams = [];
    $rq = 'INSERT INTO service '
        . '(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, '
        . 'timeperiod_tp_id2, service_description, service_alias, service_is_volatile, service_max_check_attempts, '
        . 'service_normal_check_interval, service_retry_check_interval, service_active_checks_enabled, '
        . 'service_passive_checks_enabled, service_obsess_over_service, service_check_freshness, '
        . 'service_freshness_threshold, service_event_handler_enabled, service_low_flap_threshold, '
        . 'service_high_flap_threshold, service_flap_detection_enabled, service_retain_status_information, '
        . 'service_retain_nonstatus_information, service_notification_interval, service_notification_options, '
        . 'service_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, '
        . 'service_use_only_contacts_from_host, service_stalking_options, '
        . 'service_first_notification_delay, service_recovery_notification_delay,'
        . 'service_comment, geo_coords, command_command_id_arg, command_command_id_arg2, '
        . 'service_register, service_activate, service_acknowledgement_timeout) '
        . 'VALUES ('
        . ':service_template_model_stm_id, :command_command_id, :timeperiod_tp_id, :command_command_id2, '
        . ':timeperiod_tp_id2, :service_description, :service_alias, :service_is_volatile, :service_max_check_attempts, '
        . ':service_normal_check_interval, :service_retry_check_interval, :service_active_checks_enabled, '
        . ':service_passive_checks_enabled, :service_obsess_over_service, :service_check_freshness, '
        . ':service_freshness_threshold, :service_event_handler_enabled, :service_low_flap_threshold, '
        . ':service_high_flap_threshold, :service_flap_detection_enabled, :service_retain_status_information, '
        . ':service_retain_nonstatus_information, :service_notification_interval, :service_notification_options, '
        . ':service_notifications_enabled, :contact_additive_inheritance, :cg_additive_inheritance, '
        . ':service_use_only_contacts_from_host, :service_stalking_options, '
        . ':service_first_notification_delay, :service_recovery_notification_delay, '
        . ':service_comment, :geo_coords, :command_command_id_arg, :command_command_id_arg2, '
        . ':service_register, :service_activate, :service_acknowledgement_timeout)';
    $bindParams[':service_template_model_stm_id'] = isset($submittedValues['service_template_model_stm_id']) && $submittedValues['service_template_model_stm_id'] != null
        ? (int) $submittedValues['service_template_model_stm_id'] : null;
    $bindParams[':command_command_id'] = isset($submittedValues['command_command_id']) && $submittedValues['command_command_id'] != null
        ? (int) $submittedValues['command_command_id'] : null;
    $bindParams[':timeperiod_tp_id'] = isset($submittedValues['timeperiod_tp_id']) && $submittedValues['timeperiod_tp_id'] != null
        ? (int) $submittedValues['timeperiod_tp_id'] : null;
    $bindParams[':command_command_id2'] = isset($submittedValues['command_command_id2']) && $submittedValues['command_command_id2'] != null
        ? (int) $submittedValues['command_command_id2'] : null;
    $bindParams[':timeperiod_tp_id2'] = isset($submittedValues['timeperiod_tp_id2']) && $submittedValues['timeperiod_tp_id2'] != null
        ? (int) $submittedValues['timeperiod_tp_id2'] : null;
    $bindParams[':service_description'] = isset($submittedValues['service_description']) && $submittedValues['service_description'] != null
        ? $submittedValues['service_description'] : null;
    $bindParams[':service_alias'] = isset($submittedValues['service_alias']) && $submittedValues['service_alias'] != null
        ? $submittedValues['service_alias'] : null;
    $bindParams[':service_is_volatile'] = isset($submittedValues['service_is_volatile']) && $submittedValues['service_is_volatile']['service_is_volatile'] != 2
        ? $submittedValues['service_is_volatile']['service_is_volatile'] : '2';
    $bindParams[':service_max_check_attempts'] = isset($submittedValues['service_max_check_attempts']) && $submittedValues['service_max_check_attempts'] != null
        ? (int) $submittedValues['service_max_check_attempts'] : null;
    $bindParams[':service_normal_check_interval'] = isset($submittedValues['service_normal_check_interval']) && $submittedValues['service_normal_check_interval'] != null
        ? $submittedValues['service_normal_check_interval'] : null;
    $bindParams[':service_retry_check_interval'] = isset($submittedValues['service_retry_check_interval']) && $submittedValues['service_retry_check_interval'] != null
        ? $submittedValues['service_retry_check_interval'] : null;
    $bindParams[':service_active_checks_enabled'] = isset($submittedValues['service_active_checks_enabled']['service_active_checks_enabled'])
        && $submittedValues['service_active_checks_enabled']['service_active_checks_enabled'] != 2
        ? $submittedValues['service_active_checks_enabled']['service_active_checks_enabled'] : '2';
    $bindParams[':service_passive_checks_enabled'] = isset($submittedValues['service_passive_checks_enabled']['service_passive_checks_enabled'])
        && $submittedValues['service_passive_checks_enabled']['service_passive_checks_enabled'] != 2
        ? $submittedValues['service_passive_checks_enabled']['service_passive_checks_enabled'] : '2';
    $bindParams[':service_obsess_over_service'] = isset($submittedValues['service_obsess_over_service']['service_obsess_over_service'])
        && $submittedValues['service_obsess_over_service']['service_obsess_over_service'] != 2
        ? $submittedValues['service_obsess_over_service']['service_obsess_over_service'] : '2';
    $bindParams[':service_check_freshness'] = isset($submittedValues['service_check_freshness']['service_check_freshness'])
        && $submittedValues['service_check_freshness']['service_check_freshness'] != 2
        ? $submittedValues['service_check_freshness']['service_check_freshness'] : '2';
    $bindParams[':service_freshness_threshold'] = isset($submittedValues['service_freshness_threshold']) && $submittedValues['service_freshness_threshold'] != null
        ? $submittedValues['service_freshness_threshold'] : null;
    $bindParams[':service_event_handler_enabled'] = isset($submittedValues['service_event_handler_enabled']['service_event_handler_enabled'])
        && $submittedValues['service_event_handler_enabled']['service_event_handler_enabled'] != 2
        ? $submittedValues['service_event_handler_enabled']['service_event_handler_enabled'] : '2';
    $bindParams[':service_low_flap_threshold'] = isset($submittedValues['service_low_flap_threshold']) && $submittedValues['service_low_flap_threshold'] != null
        ? $submittedValues['service_low_flap_threshold'] : null;
    $bindParams[':service_high_flap_threshold'] = isset($submittedValues['service_high_flap_threshold']) && $submittedValues['service_high_flap_threshold'] != null
        ? $submittedValues['service_high_flap_threshold'] : null;
    $bindParams[':service_flap_detection_enabled'] = isset($submittedValues['service_flap_detection_enabled']['service_flap_detection_enabled'])
        && $submittedValues['service_flap_detection_enabled']['service_flap_detection_enabled'] != 2
        ? $submittedValues['service_flap_detection_enabled']['service_flap_detection_enabled'] : '2';
    $bindParams[':service_retain_status_information'] = isset($submittedValues['service_retain_status_information']['service_retain_status_information'])
        && $submittedValues['service_retain_status_information']['service_retain_status_information'] != 2
        ? $submittedValues['service_retain_status_information']['service_retain_status_information'] : '2';
    $bindParams[':service_retain_nonstatus_information'] = isset($submittedValues['service_retain_nonstatus_information']['service_retain_nonstatus_information'])
        && $submittedValues['service_retain_nonstatus_information']['service_retain_nonstatus_information'] != 2
        ? $submittedValues['service_retain_nonstatus_information']['service_retain_nonstatus_information'] : '2';
    $bindParams[':service_notification_interval'] = isset($submittedValues['service_notification_interval']) && $submittedValues['service_notification_interval'] != null
        ? $submittedValues['service_notification_interval'] : null;
    $bindParams[':service_notification_options'] = isset($submittedValues['service_notifOpts']) && $submittedValues['service_notifOpts'] != null
        ? implode(',', array_keys($submittedValues['service_notifOpts'])) : null;
    $bindParams[':service_notifications_enabled'] = isset($submittedValues['service_notifications_enabled']['service_notifications_enabled'])
        && $submittedValues['service_notifications_enabled']['service_notifications_enabled'] != 2
        ? $submittedValues['service_notifications_enabled']['service_notifications_enabled'] : '2';
    $bindParams[':contact_additive_inheritance'] = isset($submittedValues['contact_additive_inheritance']) ? 1 : 0;
    $bindParams[':cg_additive_inheritance'] = isset($submittedValues['cg_additive_inheritance']) ? 1 : 0;
    $bindParams[':service_use_only_contacts_from_host'] = isset($submittedValues['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'])
        && $submittedValues['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'] != null
        ? $submittedValues['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'] : null;
    $bindParams[':service_stalking_options'] = isset($submittedValues['service_stalOpts']) && $submittedValues['service_stalOpts'] != null
        ? implode(',', array_keys($submittedValues['service_stalOpts'])) : null;
    $bindParams[':service_first_notification_delay'] = isset($submittedValues['service_first_notification_delay']) && $submittedValues['service_first_notification_delay'] != null
        ? $submittedValues['service_first_notification_delay'] : null;
    $bindParams[':service_recovery_notification_delay'] = isset($submittedValues['service_recovery_notification_delay']) && $submittedValues['service_recovery_notification_delay'] != null
        ? $submittedValues['service_recovery_notification_delay'] : null;
    $bindParams[':service_comment'] = isset($submittedValues['service_comment']) && $submittedValues['service_comment'] != null
        ? $submittedValues['service_comment'] : null;
    $bindParams[':geo_coords'] = isset($submittedValues['geo_coords']) && $submittedValues['geo_coords'] != null
        ? $submittedValues['geo_coords'] : null;
    $bindParams[':command_command_id_arg'] = isset($submittedValues['command_command_id_arg']) && $submittedValues['command_command_id_arg'] != null
        ? $submittedValues['command_command_id_arg'] : null;
    $bindParams[':command_command_id_arg2'] = isset($submittedValues['command_command_id_arg2']) && $submittedValues['command_command_id_arg2'] != null
        ? $submittedValues['command_command_id_arg2'] : null;
    $bindParams[':service_register'] = isset($submittedValues['service_register']) && $submittedValues['service_register'] != null
        ? $submittedValues['service_register'] : null;
    $bindParams[':service_activate'] = isset($submittedValues['service_activate']['service_activate']) && $submittedValues['service_activate']['service_activate'] != null
        ? $submittedValues['service_activate']['service_activate'] : '1';
    $bindParams[':service_acknowledgement_timeout'] = isset($submittedValues['service_acknowledgement_timeout']) && $submittedValues['service_acknowledgement_timeout'] != null
        ? $submittedValues['service_acknowledgement_timeout'] : null;
    $statement = $pearDB->prepare($rq);
    foreach ($bindParams as $param => $paramValue) {
        $statement->bindValue($param, $paramValue);
    }
    $statement->execute();
    $service_id = (int) $pearDB->lastInsertId();

    // Insert on demand macros
    if (isset($onDemandMacro)) {
        $my_tab = $onDemandMacro;
        if (isset($my_tab['nbOfMacro'])) {
            $already_stored = [];
            for ($i = 0; $i <= $my_tab['nbOfMacro']; $i++) {
                $macInput = 'macroInput_' . $i;
                $macValue = 'macroValue_' . $i;
                if (
                    isset($my_tab[$macInput])
                    && ! isset($already_stored[strtolower($my_tab[$macInput])]) && $my_tab[$macInput]
                ) {
                    $my_tab[$macInput] = str_replace('$_SERVICE', '', $my_tab[$macInput]);
                    $my_tab[$macInput] = str_replace('$', '', $my_tab[$macInput]);
                    $macName = $my_tab[$macInput];
                    $macVal = $my_tab[$macValue];
                    $rq = 'INSERT INTO on_demand_macro_service (`svc_macro_name`, `svc_macro_value`, `svc_svc_id`, '
                        . '`macro_order` ) VALUES (:svc_macro_name, :svc_macro_value, :svc_svc_id, :macro_order)';
                    $statement = $pearDB->prepare($rq);
                    $statement->bindValue(':svc_macro_name', '$_SERVICE' . strtoupper($macName) . '$', PDO::PARAM_STR);
                    $statement->bindValue(':svc_macro_value', $macVal, PDO::PARAM_STR);
                    $statement->bindValue(':svc_svc_id', (int) $service_id, PDO::PARAM_INT);
                    $statement->bindValue(':macro_order', $i, PDO::PARAM_INT);
                    $statement->execute();
                    $fields['_' . strtoupper($my_tab[$macInput]) . '_'] = $my_tab[$macValue];
                    $already_stored[strtolower($my_tab[$macInput])] = 1;
                }
            }
        }
    } elseif (isset($_REQUEST['macroInput'], $_REQUEST['macroValue'])) {
        $macroDescription = [];
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $service_id,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            $_REQUEST['macroPassword'] ?? null,
            $macroDescription,
            false,
            $submittedValues['command_command_id']
        );
    }
    $passwordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
        return $macro['macroPassword'] === '1';
    });
    $kernel = Kernel::createForWeb();
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    // If there is a vault configuration  and macros write into vault
    if ($vaultEligibilityService->shouldUseVault() && $passwordMacros !== []) {
        try {
            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
            insertServiceSecretsInVault($writeVaultRepository, $passwordMacros);
        } catch (Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($submittedValues['criticality_id'])) {
        setServiceCriticality($service_id, $submittedValues['criticality_id']);
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICE,
        object_id: $service_id,
        object_name: $submittedValues['service_description'],
        action_type: ActionLog::ACTION_TYPE_ADD,
        fields: $fields
    );

    return ['service_id' => $service_id, 'fields' => $fields];
}

function insertServiceExtInfos($serviceId = null, $submittedValues = [])
{
    if (! $serviceId) {
        return;
    }
    global $form, $pearDB, $isCloudPlatform;

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }
    // Check if image selected isn't a directory
    if (isset($submittedValues['esi_icon_image']) && strrchr('REP_', (string) $submittedValues['esi_icon_image'])) {
        $submittedValues['esi_icon_image'] = null;
    }

    $bindParams = [];
    $request = 'INSERT INTO `extended_service_information` '
        . '(`esi_id`, `service_service_id`, `esi_notes`, `esi_notes_url`, '
        . '`esi_action_url`, `esi_icon_image`, `esi_icon_image_alt`, `graph_id`) '
        . 'VALUES (NULL, :serviceId, :esi_notes, :esi_notes_url, :esi_action_url, '
        . ':esi_icon_image, :esi_icon_image_alt, :graph_id)';
    $bindParams[':serviceId'] = (int) $serviceId;
    $bindParams[':esi_notes'] = isset($submittedValues['esi_notes']) && $submittedValues['esi_notes'] != null
        ? $submittedValues['esi_notes'] : null;
    $bindParams[':esi_notes_url'] = isset($submittedValues['esi_notes_url']) && $submittedValues['esi_notes_url'] != null
        ? $submittedValues['esi_notes_url'] : null;
    $bindParams[':esi_action_url'] = isset($submittedValues['esi_action_url']) && $submittedValues['esi_action_url'] != null
        ? $submittedValues['esi_action_url'] : null;
    $bindParams[':esi_icon_image'] = isset($submittedValues['esi_icon_image']) && $submittedValues['esi_icon_image'] != null
        ? $submittedValues['esi_icon_image'] : null;
    if (! $isCloudPlatform) {
        $bindParams[':esi_icon_image_alt'] = isset($submittedValues['esi_icon_image_alt']) && $submittedValues['esi_icon_image_alt'] != null
            ? $submittedValues['esi_icon_image_alt'] : null;
        $bindParams[':graph_id'] = isset($submittedValues['graph_id']) && $submittedValues['graph_id'] != null
            ? (int) $submittedValues['graph_id'] : null;
    } else {
        $bindParams[':esi_icon_image_alt'] = null;
        $bindParams[':graph_id'] = null;
    }
    $statement = $pearDB->prepare($request);
    foreach ($bindParams as $param => $paramValue) {
        $statement->bindValue($param, $paramValue);
    }
    $statement->execute();
}

/** *************************************
 *
 * Update service informations
 * @param $service_id
 * @param $from_MC
 * @param array $params
 */
function updateService($service_id = null, $from_MC = false, $params = [])
{
    global $form, $pearDB, $centreon;

    if (! $service_id) {
        return;
    }

    $service = new CentreonService($pearDB);

    $ret = [];
    $ret = count($params) ? $params : $form->getSubmitValues();

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);
    // Retrieve vault path before updating values in database.
    $vaultPath = null;
    if ($vaultEligibilityService->shouldUseVault()) {
        $vaultPath = retrieveServiceVaultPathFromDatabase($pearDB, $service_id);
    }

    if (isset($ret['command_command_id']) && ! empty($ret['command_command_id'])) {
        $commandRepository = $kernel->getContainer()->get(ReadCommandRepositoryInterface::class);
        $command = $commandRepository->findById((int) $ret['command_command_id']);
        if ($command === null) {
            throw new InvalidArgumentException('The command ID does not exist.');
        }
        if ($command->isCentreonMonitoringAgentCommand()) {
            $ret['service_check_freshness']['service_check_freshness'] = '1';
            $ret['service_freshness_threshold'] = 120;
        }
    }
    $ret['service_description'] = $service->checkIllegalChar($ret['service_description']);

    if (isset($ret['command_command_id_arg2']) && $ret['command_command_id_arg2'] != null) {
        $ret['command_command_id_arg2'] = str_replace("\n", '//BR//', $ret['command_command_id_arg2']);
        $ret['command_command_id_arg2'] = str_replace("\t", '//T//', $ret['command_command_id_arg2']);
        $ret['command_command_id_arg2'] = str_replace("\r", '//R//', $ret['command_command_id_arg2']);
    }
    $bindParams = [];
    $rq = 'UPDATE service SET ';
    $rq .= 'service_template_model_stm_id = :serviceTemplateModelStmId, ';
    $bindParams[':serviceTemplateModelStmId'] = isset($ret['service_template_model_stm_id']) && $ret['service_template_model_stm_id'] != null
        ? $ret['service_template_model_stm_id'] : null;
    $rq .= 'command_command_id = :commandCommandId, ';
    $bindParams[':commandCommandId'] = isset($ret['command_command_id']) && $ret['command_command_id'] != null
        ? $ret['command_command_id'] : null;
    $rq .= 'timeperiod_tp_id = :timeperiodTpId, ';
    $bindParams[':timeperiodTpId'] = isset($ret['timeperiod_tp_id']) && $ret['timeperiod_tp_id'] != null
        ? $ret['timeperiod_tp_id'] : null;
    $rq .= 'command_command_id2 = :commandCommandId2, ';
    $bindParams[':commandCommandId2'] = isset($ret['command_command_id2']) && $ret['command_command_id2'] != null
        ? $ret['command_command_id2'] : null;
    // If we are doing a MC, we don't have to set name and alias field
    if (! $from_MC) {
        $rq .= 'service_description = :serviceDescription, ';
        $bindParams[':serviceDescription'] = isset($ret['service_description']) && $ret['service_description'] != null
            ? $ret['service_description'] : null;
    }
    $rq .= 'service_alias = :serviceAlias, ';
    $bindParams[':serviceAlias'] = isset($ret['service_alias']) && $ret['service_alias'] != null
        ? $ret['service_alias'] : null;
    $rq .= 'service_acknowledgement_timeout = :serviceAcknowledgementTimeout, ';
    $bindParams[':serviceAcknowledgementTimeout'] = isset($ret['service_acknowledgement_timeout']) && $ret['service_acknowledgement_timeout'] != null
        ? $ret['service_acknowledgement_timeout'] : null;
    $rq .= 'service_is_volatile = :serviceIsVolatile, ';
    $bindParams[':serviceIsVolatile'] = isset($ret['service_is_volatile']['service_is_volatile'])
        && $ret['service_is_volatile']['service_is_volatile'] != 2
        ? $ret['service_is_volatile']['service_is_volatile'] : '2';
    $rq .= 'service_max_check_attempts = :serviceMaxCheckAttempts, ';
    $bindParams[':serviceMaxCheckAttempts'] = isset($ret['service_max_check_attempts']) && $ret['service_max_check_attempts'] != null
        ? $ret['service_max_check_attempts'] : null;
    $rq .= 'service_normal_check_interval = :serviceNormalCheckInterval, ';
    $bindParams[':serviceNormalCheckInterval'] = isset($ret['service_normal_check_interval']) && $ret['service_normal_check_interval'] != null
        ? $ret['service_normal_check_interval'] : null;
    $rq .= 'service_retry_check_interval = :serviceRetryCheckInterval, ';
    $bindParams[':serviceRetryCheckInterval'] = isset($ret['service_retry_check_interval']) && $ret['service_retry_check_interval'] != null
        ? $ret['service_retry_check_interval'] : null;
    $rq .= 'service_active_checks_enabled = :serviceActiveChecksEnabled, ';
    $bindParams[':serviceActiveChecksEnabled'] = isset($ret['service_active_checks_enabled']['service_active_checks_enabled'])
        && $ret['service_active_checks_enabled']['service_active_checks_enabled'] != 2
        ? $ret['service_active_checks_enabled']['service_active_checks_enabled'] : '2';
    $rq .= 'service_passive_checks_enabled = :servicePassiveChecksEnabled, ';
    $bindParams[':servicePassiveChecksEnabled'] = isset($ret['service_passive_checks_enabled']['service_passive_checks_enabled'])
        && $ret['service_passive_checks_enabled']['service_passive_checks_enabled'] != 2
        ? $ret['service_passive_checks_enabled']['service_passive_checks_enabled'] : '2';
    $rq .= 'service_obsess_over_service = :serviceObsessOverService, ';
    $bindParams[':serviceObsessOverService'] = isset($ret['service_obsess_over_service']['service_obsess_over_service'])
        && $ret['service_obsess_over_service']['service_obsess_over_service'] != 2
        ? $ret['service_obsess_over_service']['service_obsess_over_service'] : '2';
    $rq .= 'service_check_freshness = :serviceCheckFreshness, ';
    $bindParams[':serviceCheckFreshness'] = isset($ret['service_check_freshness']['service_check_freshness'])
        && $ret['service_check_freshness']['service_check_freshness'] != 2
        ? $ret['service_check_freshness']['service_check_freshness'] : '2';
    $rq .= 'service_freshness_threshold = :serviceFreshnessThreshold, ';
    $bindParams[':serviceFreshnessThreshold'] = isset($ret['service_freshness_threshold']) && $ret['service_freshness_threshold'] != null
        ? $ret['service_freshness_threshold'] : null;
    $rq .= 'service_event_handler_enabled = :serviceEventHandlerEnabled, ';
    $bindParams[':serviceEventHandlerEnabled'] = isset($ret['service_event_handler_enabled']['service_event_handler_enabled'])
        && $ret['service_event_handler_enabled']['service_event_handler_enabled'] != 2
        ? $ret['service_event_handler_enabled']['service_event_handler_enabled'] : '2';
    $rq .= 'service_low_flap_threshold = :serviceLowFlapThreshold, ';
    $bindParams[':serviceLowFlapThreshold'] = isset($ret['service_low_flap_threshold']) && $ret['service_low_flap_threshold'] != null
        ? $ret['service_low_flap_threshold'] : null;
    $rq .= 'service_high_flap_threshold = :serviceHighFlapThreshold, ';
    $bindParams[':serviceHighFlapThreshold'] = isset($ret['service_high_flap_threshold']) && $ret['service_high_flap_threshold'] != null
        ? $ret['service_high_flap_threshold'] : null;
    $rq .= 'service_flap_detection_enabled = :serviceFlapDetectionEnabled, ';
    $bindParams[':serviceFlapDetectionEnabled'] = isset($ret['service_flap_detection_enabled']['service_flap_detection_enabled'])
        && $ret['service_flap_detection_enabled']['service_flap_detection_enabled'] != 2
        ? $ret['service_flap_detection_enabled']['service_flap_detection_enabled'] : '2';
    $rq .= 'service_retain_status_information = :serviceRetainStatusInformation, ';
    $bindParams[':serviceRetainStatusInformation'] = isset($ret['service_retain_status_information']['service_retain_status_information'])
        && $ret['service_retain_status_information']['service_retain_status_information'] != 2
        ? $ret['service_retain_status_information']['service_retain_status_information'] : '2';
    $rq .= 'service_retain_nonstatus_information = :serviceRetainNonstatusInformation, ';
    $bindParams[':serviceRetainNonstatusInformation'] = isset($ret['service_retain_nonstatus_information']['service_retain_nonstatus_information'])
        && $ret['service_retain_nonstatus_information']['service_retain_nonstatus_information'] != 2
        ? $ret['service_retain_nonstatus_information']['service_retain_nonstatus_information'] : '2';
    $rq .= 'service_notifications_enabled = :serviceNotificationsEnabled, ';
    $bindParams[':serviceNotificationsEnabled'] = isset($ret['service_notifications_enabled']['service_notifications_enabled'])
        && $ret['service_notifications_enabled']['service_notifications_enabled'] != 2
        ? $ret['service_notifications_enabled']['service_notifications_enabled'] : '2';
    $rq .= 'service_recovery_notification_delay = :serviceRecoveryNotificationDelay, ';
    $bindParams[':serviceRecoveryNotificationDelay'] = isset($ret['service_recovery_notification_delay']) && $ret['service_recovery_notification_delay'] != null
        ? $ret['service_recovery_notification_delay'] : null;
    $rq .= 'service_use_only_contacts_from_host = :serviceUseOnlyContactsFromHost, ';
    $bindParams[':serviceUseOnlyContactsFromHost'] = isset($ret['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'])
        && $ret['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'] != null
        ? $ret['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'] : null;
    $rq .= 'contact_additive_inheritance = :contactAdditiveInheritance, ';
    $bindParams[':contactAdditiveInheritance'] = isset($ret['contact_additive_inheritance']) ? 1 : 0;
    $rq .= 'cg_additive_inheritance = :cgAdditiveInheritance, ';
    $bindParams[':cgAdditiveInheritance'] = isset($ret['cg_additive_inheritance']) ? 1 : 0;
    $rq .= 'service_stalking_options = :serviceStalkingOptions, ';
    $bindParams[':serviceStalkingOptions'] = isset($ret['service_stalOpts']) && $ret['service_stalOpts'] != null
        ? implode(',', array_keys($ret['service_stalOpts'])) : null;
    $rq .= 'service_comment = :serviceComment, ';
    $bindParams[':serviceComment'] = isset($ret['service_comment']) && $ret['service_comment'] != null
        ? $ret['service_comment'] : null;
    $rq .= 'geo_coords = :geoCoords, ';
    $bindParams[':geoCoords'] = isset($ret['geo_coords']) && $ret['geo_coords'] != null
        ? $ret['geo_coords'] : null;
    $ret['command_command_id_arg'] = getCommandArgs($_POST, $ret);
    $rq .= 'command_command_id_arg = :commandCommandIdArg, ';
    $bindParams[':commandCommandIdArg'] = isset($ret['command_command_id_arg']) && $ret['command_command_id_arg'] != null
        ? $ret['command_command_id_arg'] : null;
    $rq .= 'command_command_id_arg2 = :commandCommandIdArg2, ';
    $bindParams[':commandCommandIdArg2'] = isset($ret['command_command_id_arg2']) && $ret['command_command_id_arg2'] != null
        ? $ret['command_command_id_arg2'] : null;
    $rq .= 'service_register = :serviceRegister, ';
    $bindParams[':serviceRegister'] = isset($ret['service_register']) && $ret['service_register'] != null
        ? $ret['service_register'] : null;
    $rq .= 'service_activate = :serviceActivate ';
    $bindParams[':serviceActivate'] = isset($ret['service_activate']['service_activate']) && $ret['service_activate']['service_activate'] != null
        ? $ret['service_activate']['service_activate'] : '1';
    $rq .= 'WHERE service_id = :serviceId';
    $bindParams[':serviceId'] = (int) $service_id;
    $statement = $pearDB->prepare($rq);
    foreach ($bindParams as $param => $paramValue) {
        $statement->bindValue($param, $paramValue);
    }
    $statement->execute();

    // Update demand macros
    if (isset($_REQUEST['macroInput'], $_REQUEST['macroValue'])) {
        $macroDescription = [];
        foreach ($_REQUEST as $nam => $ele) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $ele;
                }
            }
        }
        $service->insertMacro(
            $service_id,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            (! isset($_REQUEST['macroPassword']) ? 0 : $_REQUEST['macroPassword']),
            $macroDescription,
            $from_MC,
            $ret['command_command_id']
        );
    } else {
        $query = 'DELETE FROM on_demand_macro_service WHERE svc_svc_id = :svcSvcId';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':svcSvcId', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
    }

    if ($vaultEligibilityService->shouldUseVault()) {
        /** @var ReadVaultRepositoryInterface $readVaultRepository */
        $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

        /** @var WriteVaultRepositoryInterface $writeVaultRepository */
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
        $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
        try {
            updateServiceSecretsInVault(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $vaultPath,
                (int) $service_id,
                $service->getFormattedMacros(),
            );
        } catch (Throwable $ex) {
            error_log((string) $ex);
        }
    }

    if (isset($ret['criticality_id'])) {
        setServiceCriticality($service_id, $ret['criticality_id']);
    }

    $centreon->user->access->updateACL(['type' => 'SERVICE', 'id' => $service_id, 'action' => 'UPDATE']);

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICE,
        object_id: $service_id,
        object_name: $ret['service_description'],
        action_type: ActionLog::ACTION_TYPE_CHANGE,
        fields: $fields
    );
}

function updateService_MC($service_id = null, $params = [])
{
    if (! $service_id) {
        return;
    }
    global $form, $pearDB, $centreon;

    $service = new CentreonService($pearDB);

    $ret = [];
    $ret = count($params) ? $params : $form->getSubmitValues();

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    $isServiceTemplate = isset($ret['service_register']) && $ret['service_register'] === '0';
    /** @var VaultEligibilityService $vaultEligibilityService */
    $vaultEligibilityService = $kernel->getContainer()->get(VaultEligibilityService::class);

    // Retrieve UUID for vault path before updating values in database.
    $vaultPath = null;
    if ($vaultEligibilityService->shouldUseVault()) {
        $vaultPath = retrieveServiceVaultPathFromDatabase($pearDB, $service_id);
    }

    if (! empty($ret['command_command_id'])) {
        $commandRepository = $kernel->getContainer()->get(ReadCommandRepositoryInterface::class);
        $command = $commandRepository->findById((int) $ret['command_command_id']);
        if ($command === null) {
            throw new InvalidArgumentException('The command ID does not exist.');
        }
        if ($command->isCentreonMonitoringAgentCommand()) {
            $ret['service_check_freshness']['service_check_freshness'] = '1';
            $ret['service_freshness_threshold'] = 120;
        }
    }

    if (isset($ret['sg_name'])) {
        $ret['sg_name'] = $centreon->checkIllegalChar($ret['sg_name']);
    }

    if (isset($ret['command_command_id_arg']) && $ret['command_command_id_arg'] != null) {
        $ret['command_command_id_arg'] = str_replace("\n", '//BR//', $ret['command_command_id_arg']);
        $ret['command_command_id_arg'] = str_replace("\t", '//T//', $ret['command_command_id_arg']);
        $ret['command_command_id_arg'] = str_replace("\r", '//R//', $ret['command_command_id_arg']);
    }
    if (isset($ret['command_command_id_arg2']) && $ret['command_command_id_arg2'] != null) {
        $ret['command_command_id_arg2'] = str_replace("\n", '//BR//', $ret['command_command_id_arg2']);
        $ret['command_command_id_arg2'] = str_replace("\t", '//T//', $ret['command_command_id_arg2']);
        $ret['command_command_id_arg2'] = str_replace("\r", '//R//', $ret['command_command_id_arg2']);

    }

    $bindParams = [];
    $rq = 'UPDATE service SET ';
    if (isset($ret['service_template_model_stm_id']) && $ret['service_template_model_stm_id'] != null) {
        $rq .= 'service_template_model_stm_id = :serviceTemplateModelStmId, ';
        $bindParams[':serviceTemplateModelStmId'] = $ret['service_template_model_stm_id'];
    }
    if (isset($ret['command_command_id']) && $ret['command_command_id'] != null) {
        $rq .= 'command_command_id = :commandCommandId, ';
        $bindParams[':commandCommandId'] = $ret['command_command_id'];
    }
    if (isset($ret['timeperiod_tp_id']) && $ret['timeperiod_tp_id'] != null) {
        $rq .= 'timeperiod_tp_id = :timeperiodTpId, ';
        $bindParams[':timeperiodTpId'] = $ret['timeperiod_tp_id'];
    }
    if (isset($ret['command_command_id2']) && $ret['command_command_id2'] != null) {
        $rq .= 'command_command_id2 = :commandCommandId2, ';
        $bindParams[':commandCommandId2'] = $ret['command_command_id2'];
    }
    if (isset($ret['service_alias']) && $ret['service_alias'] != null) {
        $rq .= 'service_alias = :serviceAlias, ';
        $bindParams[':serviceAlias'] = $ret['service_alias'];
    }
    if (
        isset($ret['service_is_volatile']['service_is_volatile'])
        && $ret['service_is_volatile']['service_is_volatile'] != 2
    ) {
        $rq .= 'service_is_volatile = :serviceIsVolatile, ';
        $bindParams[':serviceIsVolatile'] = $ret['service_is_volatile']['service_is_volatile'];
    }
    if (isset($ret['service_max_check_attempts']) && $ret['service_max_check_attempts'] != null) {
        $rq .= 'service_max_check_attempts = :serviceMaxCheckAttempts, ';
        $bindParams[':serviceMaxCheckAttempts'] = $ret['service_max_check_attempts'];
    }
    if (isset($ret['service_acknowledgement_timeout']) && $ret['service_acknowledgement_timeout'] != null) {
        $rq .= 'service_acknowledgement_timeout = :serviceAcknowledgementTimeout, ';
        $bindParams[':serviceAcknowledgementTimeout'] = $ret['service_acknowledgement_timeout'];
    }
    if (isset($ret['service_normal_check_interval']) && $ret['service_normal_check_interval'] != null) {
        $rq .= 'service_normal_check_interval = :serviceNormalCheckInterval, ';
        $bindParams[':serviceNormalCheckInterval'] = $ret['service_normal_check_interval'];
    }
    if (isset($ret['service_retry_check_interval']) && $ret['service_retry_check_interval'] != null) {
        $rq .= 'service_retry_check_interval = :serviceRetryCheckInterval, ';
        $bindParams[':serviceRetryCheckInterval'] = $ret['service_retry_check_interval'];
    }
    if (isset($ret['service_active_checks_enabled']['service_active_checks_enabled'])) {
        $rq .= 'service_active_checks_enabled = :serviceActiveChecksEnabled, ';
        $bindParams[':serviceActiveChecksEnabled'] = $ret['service_active_checks_enabled']['service_active_checks_enabled'];
    }
    if (isset($ret['service_passive_checks_enabled']['service_passive_checks_enabled'])) {
        $rq .= 'service_passive_checks_enabled = :servicePassiveChecksEnabled, ';
        $bindParams[':servicePassiveChecksEnabled'] = $ret['service_passive_checks_enabled']['service_passive_checks_enabled'];
    }
    if (isset($ret['service_obsess_over_service']['service_obsess_over_service'])) {
        $rq .= 'service_obsess_over_service = :serviceObsessOverService, ';
        $bindParams[':serviceObsessOverService'] = $ret['service_obsess_over_service']['service_obsess_over_service'];
    }
    if (isset($ret['service_check_freshness']['service_check_freshness'])) {
        $rq .= 'service_check_freshness = :serviceCheckFreshness, ';
        $bindParams[':serviceCheckFreshness'] = $ret['service_check_freshness']['service_check_freshness'];
    }
    if (isset($ret['service_freshness_threshold']) && $ret['service_freshness_threshold'] != null) {
        $rq .= 'service_freshness_threshold = :serviceFreshnessThreshold, ';
        $bindParams[':serviceFreshnessThreshold'] = $ret['service_freshness_threshold'];
    }
    if (isset($ret['service_event_handler_enabled']['service_event_handler_enabled'])) {
        $rq .= 'service_event_handler_enabled = :serviceEventHandlerEnabled, ';
        $bindParams[':serviceEventHandlerEnabled'] = $ret['service_event_handler_enabled']['service_event_handler_enabled'];
    }
    if (isset($ret['service_low_flap_threshold']) && $ret['service_low_flap_threshold'] != null) {
        $rq .= 'service_low_flap_threshold = :serviceLowFlapThreshold, ';
        $bindParams[':serviceLowFlapThreshold'] = $ret['service_low_flap_threshold'];
    }
    if (isset($ret['service_high_flap_threshold']) && $ret['service_high_flap_threshold'] != null) {
        $rq .= 'service_high_flap_threshold = :serviceHighFlapThreshold, ';
        $bindParams[':serviceHighFlapThreshold'] = $ret['service_high_flap_threshold'];
    }
    if (isset($ret['service_flap_detection_enabled']['service_flap_detection_enabled'])) {
        $rq .= 'service_flap_detection_enabled = :serviceFlapDetectionEnabled, ';
        $bindParams[':serviceFlapDetectionEnabled'] = $ret['service_flap_detection_enabled']['service_flap_detection_enabled'];
    }
    if (isset($ret['service_retain_status_information']['service_retain_status_information'])) {
        $rq .= 'service_retain_status_information = :serviceRetainStatusInformation, ';
        $bindParams[':serviceRetainStatusInformation'] = $ret['service_retain_status_information']['service_retain_status_information'];
    }
    if (isset($ret['service_retain_nonstatus_information']['service_retain_nonstatus_information'])) {
        $rq .= 'service_retain_nonstatus_information = :serviceRetainNonstatusInformation, ';
        $bindParams[':serviceRetainNonstatusInformation'] = $ret['service_retain_nonstatus_information']['service_retain_nonstatus_information'];
    }
    if (isset($ret['service_notifications_enabled']['service_notifications_enabled'])) {
        $rq .= 'service_notifications_enabled = :serviceNotificationsEnabled, ';
        $bindParams[':serviceNotificationsEnabled'] = $ret['service_notifications_enabled']['service_notifications_enabled'];
    }
    if (isset($ret['service_recovery_notification_delay']) && $ret['service_recovery_notification_delay'] != null) {
        $rq .= 'service_recovery_notification_delay = :serviceRecoveryNotificationDelay, ';
        $bindParams[':serviceRecoveryNotificationDelay'] = $ret['service_recovery_notification_delay'];
    }
    if (
        isset($ret['mc_contact_additive_inheritance']['mc_contact_additive_inheritance'])
        && in_array($ret['mc_contact_additive_inheritance']['mc_contact_additive_inheritance'], ['0', '1'])
    ) {
        $rq .= 'contact_additive_inheritance = :contactAdditiveInheritance, ';
        $bindParams[':contactAdditiveInheritance'] = $ret['mc_contact_additive_inheritance']['mc_contact_additive_inheritance'];
    }
    if (
        isset($ret['mc_cg_additive_inheritance']['mc_cg_additive_inheritance'])
        && in_array($ret['mc_cg_additive_inheritance']['mc_cg_additive_inheritance'], ['0', '1'])
    ) {
        $rq .= 'cg_additive_inheritance = :cgAdditiveInheritance, ';
        $bindParams[':cgAdditiveInheritance'] = $ret['mc_cg_additive_inheritance']['mc_cg_additive_inheritance'];
    }
    if (isset($ret['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'])) {
        $rq .= 'service_use_only_contacts_from_host = :serviceUseOnlyContactsFromHost, ';
        $bindParams[':serviceUseOnlyContactsFromHost'] = $ret['service_use_only_contacts_from_host']['service_use_only_contacts_from_host'];
    }
    if (isset($ret['service_stalOpts']) && $ret['service_stalOpts'] != null) {
        $rq .= 'service_stalking_options = :serviceStalkingOptions, ';
        $bindParams[':serviceStalkingOptions'] = implode(',', array_keys($ret['service_stalOpts']));
    }
    if (isset($ret['service_comment']) && $ret['service_comment'] != null) {
        $rq .= 'service_comment = :serviceComment, ';
        $bindParams[':serviceComment'] = $ret['service_comment'];
    }
    $ret['command_command_id_arg'] = getCommandArgs($_POST, $ret);
    if (isset($ret['command_command_id_arg']) && $ret['command_command_id_arg'] != null) {
        $rq .= 'command_command_id_arg = :commandCommandIdArg, ';
        $bindParams[':commandCommandIdArg'] = $ret['command_command_id_arg'];
    }
    if (isset($ret['command_command_id_arg2']) && $ret['command_command_id_arg2'] != null) {
        $rq .= 'command_command_id_arg2 = :commandCommandIdArg2, ';
        $bindParams[':commandCommandIdArg2'] = $ret['command_command_id_arg2'];
    }
    if (isset($ret['service_register']) && $ret['service_register'] != null) {
        $rq .= 'service_register = :serviceRegister, ';
        $bindParams[':serviceRegister'] = $ret['service_register'];
    }
    if (isset($ret['geo_coords']) && $ret['geo_coords'] != null) {
        $rq .= 'geo_coords = :geoCoords, ';
        $bindParams[':geoCoords'] = $ret['geo_coords'];
    }

    if (! $isServiceTemplate) {
        if (isset($ret['service_activate']['service_activate']) && $ret['service_activate']['service_activate'] != null) {
            $rq .= 'service_activate = :serviceActivate, ';
            $bindParams[':serviceActivate'] = $ret['service_activate']['service_activate'];
        }
    } else {
        $rq .= 'service_activate = :serviceActivate, ';
        $bindParams[':serviceActivate'] = '1';
    }

    if (strcmp('UPDATE service SET ', $rq)) {
        // Delete last ',' in request
        $rq[strlen($rq) - 2] = ' ';
        $rq .= 'WHERE service_id = :serviceId';
        $bindParams[':serviceId'] = (int) $service_id;
        $statement = $pearDB->prepare($rq);
        foreach ($bindParams as $param => $paramValue) {
            $statement->bindValue($param, $paramValue);
        }
        $statement->execute();
    }

    // Update on demand macros
    $macroDescription = [];
    foreach ($_REQUEST as $nam => $ele) {
        if (preg_match_all("/^macroDescription_(\w+)$/", $nam, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $macroDescription[$match[1]] = $ele;
            }
        }
    }
    if (isset($_REQUEST['macroInput'], $_REQUEST['macroValue'])) {
        $service->insertMacro(
            $service_id,
            $_REQUEST['macroInput'],
            $_REQUEST['macroValue'],
            $_REQUEST['macroPassword'] ?? [],
            $macroDescription,
            true,
            false,
            $_REQUEST['macroFrom']
        );
    }
    if (isset($ret['criticality_id']) && $ret['criticality_id']) {
        setServiceCriticality($service_id, $ret['criticality_id']);
    }

    // If there is a vault configuration write into vault
    if ($vaultEligibilityService->shouldUseVault()) {
        try {
            /** @var ReadVaultRepositoryInterface $readVaultRepository */
            $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);

            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);

            $updatedPasswordMacros = array_filter($service->getFormattedMacros(), function ($macro) {
                return $macro['macroPassword'] === '1'
                    && ! str_starts_with($macro['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN);
            });
            updateServiceSecretsInVaultFromMC(
                $readVaultRepository,
                $writeVaultRepository,
                $logger,
                $vaultPath,
                (int) $service_id,
                $updatedPasswordMacros
            );
        } catch (Throwable $ex) {
            error_log((string) $ex);
        }
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICE,
        object_id: $service_id,
        object_name: $ret['service_description'] ?? '',
        action_type: ActionLog::ACTION_TYPE_MASS_CHANGE,
        fields: $fields
    );
}

// For Nagios 3
function updateServiceContact($service_id = null, $ret = [])
{
    if (! $service_id) {
        return;
    }
    global $form;
    global $pearDB;
    $deleteStatement = $pearDB->prepare(
        'DELETE FROM contact_service_relation WHERE service_service_id = :serviceId'
    );
    $deleteStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $deleteStatement->execute();
    $ret = $ret['service_cs'] ?? $form->getSubmitValue('service_cs');

    $loopCount = (is_countable($ret)) ? count($ret) : 0;

    if ($loopCount > 0) {
        $insertStatement = $pearDB->prepare(
            'INSERT INTO contact_service_relation (contact_id, service_service_id) VALUES (:contactId, :serviceId)'
        );
        for ($i = 0; $i < $loopCount; $i++) {
            $insertStatement->bindValue(':contactId', (int) $ret[$i], PDO::PARAM_INT);
            $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
            $insertStatement->execute();
        }
    }
}

function updateServiceContactGroup($service_id = null, $ret = [])
{
    if (! $service_id) {
        return;
    }
    global $form;
    global $pearDB;
    $deleteStatement = $pearDB->prepare(
        'DELETE FROM contactgroup_service_relation WHERE service_service_id = :serviceId'
    );
    $deleteStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $deleteStatement->execute();

    $ret = $ret['service_cgs'] ?? $form->getSubmitValue('service_cgs');

    $cg = new CentreonContactgroup($pearDB);

    if (is_array($ret)) {
        $insertStatement = $pearDB->prepare(
            'INSERT INTO contactgroup_service_relation (contactgroup_cg_id, service_service_id) '
            . 'VALUES (:cgId, :serviceId)'
        );
        $counter = count($ret);
        for ($i = 0; $i < $counter; $i++) {
            if (! is_numeric($ret[$i])) {
                $res = $cg->insertLdapGroup($ret[$i]);
                if ($res != 0) {
                    $ret[$i] = $res;
                } else {
                    continue;
                }
            }
            if (isset($ret[$i]) && $ret[$i] && $ret[$i] != '') {
                $insertStatement->bindValue(':cgId', (int) $ret[$i], PDO::PARAM_INT);
                $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                $insertStatement->execute();
            }
        }
    }
}

/**
 * @param ?int $serviceId
 * @param array $submittedValues
 */
function updateServiceNotifs(?int $serviceId = null, array $submittedValues = [])
{
    if (! $serviceId) {
        return;
    }

    global $form, $pearDB;

    $notificationOptions = $submittedValues['service_notifOpts'] ?? $form->getSubmitValue('service_notifOpts');

    try {
        $query = $pearDB->prepareQuery(
            <<<'SQL'
                    UPDATE `service` SET `service_notification_options` = :service_notification_options
                    WHERE `service_id` = :service_id
                SQL
        );

        $queryParams = [
            'service_id' => $serviceId,
            'service_notification_options' => $notificationOptions ? implode(',', array_keys($notificationOptions)) : null,
        ];

        $pearDB->executePreparedQuery($query, $queryParams);
    } catch (PDOException $exception) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while updating notification options: {$exception->getMessage()}",
            customContext: ['service Id' => $serviceId],
            exception: $exception
        );

        throw $exception;
    }
}

// For massive change. incremental mode
function updateServiceNotifs_MC($service_id = null)
{
    if (! $service_id) {
        return;
    }
    global $form;
    global $pearDB;

    $selectStatement = $pearDB->prepare('SELECT service_notification_options FROM service WHERE service_id = :serviceId LIMIT 1');
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $dbResult = $selectStatement;
    $service = [];
    $service = array_map('db2str', $dbResult->fetch());
    $service = array_map('myDecode', $service);

    $ret = $form->getSubmitValue('service_notifOpts');

    if (is_array($ret)) {
        if (isset($service['service_notification_options']) && $service['service_notification_options'] != null) {
            $temp = $service['service_notification_options'] . ',' . implode(',', array_keys($ret));
        } else {
            $temp = implode(',', array_keys($ret));
        }
    }

    if (isset($temp) && $temp != null) {
        $statement = $pearDB->prepare('UPDATE service SET service_notification_options = :notifOpts WHERE service_id = :serviceId');
        $statement->bindValue(':notifOpts', trim($temp, ','));
        $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
    }
}

function updateServiceNotifOptionInterval($service_id = null, $ret = [])
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    if (isset($ret['service_notification_interval'])) {
        $ret = $ret['service_notification_interval'];
    } else {
        $ret = $form->getSubmitValue('service_notification_interval');
    }

    $statement = $pearDB->prepare('UPDATE service SET service_notification_interval = :notifInterval WHERE service_id = :serviceId');
    $statement->bindValue(':notifInterval', isset($ret) && $ret != null ? $ret : null);
    $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $statement->execute();
}

// For massive change. incremental mode
function updateServiceNotifOptionInterval_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $ret = $form->getSubmitValue('service_notification_interval');

    if (isset($ret) && $ret != null) {
        $statement = $pearDB->prepare('UPDATE service SET service_notification_interval = :notifInterval WHERE service_id = :serviceId');
        $statement->bindValue(':notifInterval', $ret);
        $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
    }
}

/**
 * @param int $serviceId
 * @param array $ret
 *
 * @throws CentreonDbException
 */
function updateServiceNotifOptionTimeperiod(int $serviceId, $ret = [])
{
    global $pearDB;

    try {
        $queryParams = [];
        $request = <<<'SQL'
            UPDATE `service` SET `timeperiod_tp_id2` = :timeperiod_tp_id2
            WHERE `service_id` = :service_id
            SQL;

        $stmt = $pearDB->prepareQuery($request);
        $queryParams['service_id'] = $serviceId;

        $queryParams['timeperiod_tp_id2'] = ! empty($ret['timeperiod_tp_id2']) ? $ret['timeperiod_tp_id2'] : null;

        $pearDB->executePreparedQuery($stmt, $queryParams);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: 'Error while updating service notification timeperiod: ' . $exception->getMessage(),
            customContext: ['service_id' => $serviceId],
            exception: $exception
        );

        throw $exception;
    }
}

// For massive change. incremental mode
function updateServiceNotifOptionTimeperiod_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $ret = $form->getSubmitValue('timeperiod_tp_id2');

    if (isset($ret) && $ret != null) {
        $statement = $pearDB->prepare('UPDATE service SET timeperiod_tp_id2 = :timeperiodId WHERE service_id = :serviceId');
        $statement->bindValue(':timeperiodId', (int) $ret, PDO::PARAM_INT);
        $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
    }
}

function updateServiceNotifOptionFirstNotificationDelay($service_id = null, $ret = [])
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    if (isset($ret['service_first_notification_delay'])) {
        $ret = $ret['service_first_notification_delay'];
    } else {
        $ret = $form->getSubmitValue('service_first_notification_delay');
    }

    $statement = $pearDB->prepare('UPDATE service SET service_first_notification_delay = :firstNotifDelay WHERE service_id = :serviceId');
    $statement->bindValue(':firstNotifDelay', isset($ret) && $ret != null ? $ret : null);
    $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $statement->execute();
}

// For massive change. incremental mode
function updateServiceNotifOptionFirstNotificationDelay_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $ret = $form->getSubmitValue('service_first_notification_delay');

    if (isset($ret) && $ret != null) {
        $statement = $pearDB->prepare('UPDATE service SET service_first_notification_delay = :firstNotifDelay WHERE service_id = :serviceId');
        $statement->bindValue(':firstNotifDelay', $ret);
        $statement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceContactGroup_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $selectStatement = $pearDB->prepare('SELECT contactgroup_cg_id FROM contactgroup_service_relation WHERE service_service_id = :serviceId');
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $cgs = [];
    while ($arr = $selectStatement->fetch()) {
        $cgs[$arr['contactgroup_cg_id']] = $arr['contactgroup_cg_id'];
    }
    $ret = $form->getSubmitValue('service_cgs');
    $cg = new CentreonContactgroup($pearDB);
    if (is_array($ret)) {
        $insertStatement = $pearDB->prepare('INSERT INTO contactgroup_service_relation (contactgroup_cg_id, service_service_id) VALUES (:cgId, :serviceId)');
        $counter = count($ret);
        for ($i = 0; $i < $counter; $i++) {
            if (! isset($cgs[$ret[$i]])) {
                if (! is_numeric($ret[$i])) {
                    $res = $cg->insertLdapGroup($ret[$i]);
                    if ($res != 0) {
                        $ret[$i] = $res;
                    } else {
                        continue;
                    }
                }
                if (isset($ret[$i]) && $ret[$i] && $ret[$i] != '') {
                    $insertStatement->bindValue(':cgId', (int) $ret[$i], PDO::PARAM_INT);
                    $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                    $insertStatement->execute();
                }
            }
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceContact_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $selectStatement = $pearDB->prepare('SELECT contact_id FROM contact_service_relation WHERE service_service_id = :serviceId');
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $cs = [];
    while ($arr = $selectStatement->fetch()) {
        $cs[$arr['contact_id']] = $arr['contact_id'];
    }
    $ret = $form->getSubmitValue('service_cs');
    if (is_array($ret)) {
        $insertStatement = $pearDB->prepare('INSERT INTO contact_service_relation (contact_id, service_service_id) VALUES (:contactId, :serviceId)');
        $counter = count($ret);
        for ($i = 0; $i < $counter; $i++) {
            if (! isset($cs[$ret[$i]])) {
                $insertStatement->bindValue(':contactId', (int) $ret[$i], PDO::PARAM_INT);
                $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                $insertStatement->execute();
            }
        }
    }
}

function updateServiceServiceGroup($service_id = null, $ret = [])
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $deleteStatement = $pearDB->prepare(
        'DELETE FROM servicegroup_relation WHERE service_service_id = :serviceId'
    );
    $deleteStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $deleteStatement->execute();

    $ret = $ret['service_sgs'] ?? CentreonUtils::mergeWithInitialValues($form, 'service_sgs');
    $insertHgSgStatement = $pearDB->prepare(
        'INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) '
        . 'VALUES (NULL, :hgId, :serviceId, :sgId)'
    );
    $insertHostSgStatement = $pearDB->prepare(
        'INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) '
        . 'VALUES (:hostId, NULL, :serviceId, :sgId)'
    );
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        // We need to record each relation for host / hostgroup selected
        if (isset($ret['service_hPars'])) {
            $ret1 = CentreonUtils::mergeWithInitialValues($form, 'service_hPars');
        } else {
            $ret1 = getMyServiceHosts($service_id);
        }
        if (isset($ret['service_hgPars'])) {
            $ret2 = CentreonUtils::mergeWithInitialValues($form, 'service_hgPars');
        } else {
            $ret2 = getMyServiceHostGroups($service_id);
        }
        if (count($ret2)) {
            foreach ($ret2 as $value) {
                $insertHgSgStatement->bindValue(':hgId', (int) $value, PDO::PARAM_INT);
                $insertHgSgStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                $insertHgSgStatement->bindValue(':sgId', (int) $ret[$i], PDO::PARAM_INT);
                $insertHgSgStatement->execute();
            }
        } elseif (count($ret1)) {
            foreach ($ret1 as $value) {
                $insertHostSgStatement->bindValue(':hostId', (int) $value, PDO::PARAM_INT);
                $insertHostSgStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                $insertHostSgStatement->bindValue(':sgId', (int) $ret[$i], PDO::PARAM_INT);
                $insertHostSgStatement->execute();
            }
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceServiceGroup_MC($service_id = null)
{
    global $form, $pearDB;
    if (! $service_id) {
        return;
    }
    $selectStatement = $pearDB->prepare(
        'SELECT host_host_id, hostgroup_hg_id, servicegroup_sg_id '
        . 'FROM servicegroup_relation WHERE service_service_id = :serviceId'
    );
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $hsgs = [];
    $hgsgs = [];
    while ($arr = $selectStatement->fetch()) {
        if ($arr['host_host_id']) {
            $hsgs[$arr['host_host_id']][] = $arr['servicegroup_sg_id'];
        }
        if ($arr['hostgroup_hg_id']) {
            $hgsgs[$arr['hostgroup_hg_id']][] = $arr['servicegroup_sg_id'];
        }
    }
    $ret = $form->getSubmitValue('service_sgs');
    if (is_array($ret)) {
        $insertHgSgStatement = $pearDB->prepare(
            'INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) '
            . 'VALUES (NULL, :hgId, :serviceId, :sgId)'
        );
        $insertHostSgStatement = $pearDB->prepare(
            'INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) '
            . 'VALUES (:hostId, NULL, :serviceId, :sgId)'
        );
        $counter = count($ret);
        for ($i = 0; $i < $counter; $i++) {
            // We need to record each relation for host / hostgroup selected
            $ret1 = getMyServiceHosts($service_id);
            $ret2 = getMyServiceHostGroups($service_id);
            if (count($ret2)) {
                foreach ($ret2 as $hg) {
                    if (! in_array($ret[$i], $hgsgs[$hg])) {
                        $insertHgSgStatement->bindValue(':hgId', (int) $hg, PDO::PARAM_INT);
                        $insertHgSgStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                        $insertHgSgStatement->bindValue(':sgId', (int) $ret[$i], PDO::PARAM_INT);
                        $insertHgSgStatement->execute();
                    }
                }
            } elseif (count($ret1)) {
                foreach ($ret1 as $h) {
                    if (! isset($hsgs[$h]) || ! in_array($ret[$i], $hsgs[$h])) {
                        $insertHostSgStatement->bindValue(':hostId', (int) $h, PDO::PARAM_INT);
                        $insertHostSgStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                        $insertHostSgStatement->bindValue(':sgId', (int) $ret[$i], PDO::PARAM_INT);
                        $insertHostSgStatement->execute();
                    }
                }
            }
        }
    }
}

function updateServiceTrap($service_id = null, $ret = [])
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $deleteStatement = $pearDB->prepare('DELETE FROM traps_service_relation WHERE service_id = :serviceId');
    $deleteStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $deleteStatement->execute();
    $ret = $ret['service_traps'] ?? $form->getSubmitValue('service_traps');

    if (is_array($ret)) {
        $insertStatement = $pearDB->prepare(
            'INSERT INTO traps_service_relation (traps_id, service_id) VALUES (:trapsId, :serviceId)'
        );
        foreach ($ret as $trapsId) {
            $insertStatement->bindValue(':trapsId', (int) $trapsId, PDO::PARAM_INT);
            $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
            $insertStatement->execute();
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceTrap_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $selectStatement = $pearDB->prepare(
        'SELECT traps_id FROM traps_service_relation WHERE service_id = :serviceId'
    );
    $selectStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
    $selectStatement->execute();
    $traps = [];
    while ($arr = $selectStatement->fetch()) {
        $traps[$arr['traps_id']] = $arr['traps_id'];
    }
    $ret = $form->getSubmitValue('service_traps');
    if (is_array($ret)) {
        $insertStatement = $pearDB->prepare(
            'INSERT INTO traps_service_relation (traps_id, service_id) VALUES (:trapsId, :serviceId)'
        );
        foreach ($ret as $trapsId) {
            if (! isset($traps[$trapsId])) {
                $insertStatement->bindValue(':trapsId', (int) $trapsId, PDO::PARAM_INT);
                $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
                $insertStatement->execute();
            }
        }
    }
}

function updateServiceHost($service_id = null, $ret = [], $from_MC = false)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $ret1 = [];
    $ret2 = [];
    if (isset($ret['service_hPars'])) {
        $ret1 = $ret['service_hPars'];
    } else {
        $ret1 = CentreonUtils::mergeWithInitialValues($form, 'service_hPars');
    }
    if (isset($ret['service_hgPars'])) {
        $ret2 = $ret['service_hgPars'];
    } else {
        $ret2 = CentreonUtils::mergeWithInitialValues($form, 'service_hgPars');
    }

    // Get actual config
    $statement = $pearDB->prepare(
        'SELECT host_host_id FROM escalation_service_relation WHERE service_service_id = :service_id'
    );
    $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
    $statement->execute();
    $cacheEsc = [];
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cacheEsc[$data['host_host_id']] = 1;
    }

    // Get actual config
    $statement = $pearDB->prepare(
        'SELECT host_host_id FROM host_service_relation WHERE service_service_id = :service_id'
    );
    $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
    $statement->execute();
    $cache = [];
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $cache[$data['host_host_id']] = 1;
    }

    if (count($ret1) == 1) {
        foreach ($cache as $host_id => $flag) {
            if (! isset($cacheEsc[$host_id]) && count($cacheEsc)) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        UPDATE escalation_service_relation
                        SET host_host_id = :host_host_id
                        WHERE service_service_id = :service_id
                        SQL
                );
                $statement->bindValue(':host_host_id', (int) $ret1[0], PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
                $statement->execute();
            }
        }
    } else {
        foreach ($cache as $host_id) {
            if (! isset($cache[$host_id]) && count($cacheEsc)) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        DELETE FROM escalation_service_relation
                        WHERE host_host_id = :host_host_id
                          AND service_service_id = :service_id
                        SQL
                );
                $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
                $statement->bindValue(':host_host_id', (int) $ret1[0], PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    if (! $from_MC) {
        $statement = $pearDB->prepare(
            'DELETE FROM host_service_relation WHERE service_service_id = :service_id'
        );
        $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
        $statement->execute();
    } else {
        // Purge service to host relations
        if (count($ret1)) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    DELETE FROM host_service_relation
                    WHERE service_service_id = :service_id
                    AND host_host_id IS NOT NULL
                    SQL
            );
            $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
            $statement->execute();
        }
        // Purge service to hostgroup relations
        if (count($ret2)) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    DELETE FROM host_service_relation
                    WHERE service_service_id = :service_id
                    AND hostgroup_hg_id IS NOT NULL
                    SQL
            );
            $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    if (count($ret2)) {
        $counter = count($ret2);
        for ($i = 0; $i < $counter; $i++) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                    VALUES (:host_group_id, NULL, NULL, :service_id)
                    SQL
            );
            $statement->bindValue(':host_group_id', (int) $ret2[$i], PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, null, $ret2[$i]);
        }
    } elseif (count($ret1)) {
        $counter = count($ret1);
        for ($i = 0; $i < $counter; $i++) {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                    VALUES (NULL, :host_id, NULL, :service_id)
                    SQL
            );
            $statement->bindValue(':host_id', (int) $ret1[$i], PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
            $statement->execute();
            setHostChangeFlag($pearDB, $ret1[$i], null);
        }
    }
}

// For massive change. We just add the new list if the elem doesn't exist yet
function updateServiceHost_MC($service_id = null)
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT * FROM host_service_relation WHERE service_service_id = :service_id
            SQL
    );
    $statement->bindValue(':service_id', $service_id, PDO::PARAM_INT);

    $statement->execute();
    $hsvs = [];
    $hgsvs = [];

    while ($arr = $statement->fetch()) {
        if ($arr['host_host_id']) {
            $hsvs[$arr['host_host_id']] = $arr['host_host_id'];
        }
        if ($arr['hostgroup_hg_id']) {
            $hgsvs[$arr['hostgroup_hg_id']] = $arr['hostgroup_hg_id'];
        }
    }

    $ret1 = $form->getSubmitValue('service_hPars');
    $ret2 = $form->getSubmitValue('service_hgPars');
    if (is_array($ret2)) {
        $counter = count($ret2);
        for ($i = 0; $i < $counter; $i++) {
            if (! isset($hgsvs[$ret2[$i]])) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        DELETE FROM host_service_relation
                        WHERE service_service_id = :service_id
                        AND host_host_id IS NOT NULL
                        SQL
                );
                $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
                $statement->execute();

                $statement = $pearDB->prepare(
                    <<<'SQL'
                        INSERT INTO host_service_relation
                        (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                        VALUES (:host_group_id, NULL, NULL, :service_id)
                        SQL
                );
                $statement->bindValue(':host_group_id', (int) $ret2[$i], PDO::PARAM_INT);
                $statement->bindValue(':service_id', $service_id, PDO::PARAM_INT);
                $statement->execute();

                setHostChangeFlag($pearDB, null, $ret2[$i]);
            }
        }
    } elseif (is_array($ret1)) {
        $counter = count($ret1);
        for ($i = 0; $i < $counter; $i++) {
            if (! isset($hsvs[$ret1[$i]])) {
                $statement = $pearDB->prepare(
                    <<<'SQL'
                        DELETE FROM host_service_relation
                        WHERE service_service_id = :service_id
                        AND hostgroup_hg_id IS NOT NULL
                        SQL
                );
                $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
                $statement->execute();

                $statement = $pearDB->prepare(
                    <<<'SQL'
                            INSERT INTO host_service_relation
                            (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
                            VALUES (NULL, :host_id, NULL, :service_id)
                        SQL
                );
                $statement->bindValue(':host_id', (int) $ret1[$i], PDO::PARAM_INT);
                $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
                $statement->execute();

                setHostChangeFlag($pearDB, $ret1[$i], null);
            }
        }
    }
}

function updateServiceExtInfos($serviceId = null, $submittedValues = [])
{
    global $form, $pearDB, $isCloudPlatform;

    if (! $serviceId) {
        return;
    }

    if (! count($submittedValues)) {
        $submittedValues = $form->getSubmitValues();
    }
    // Check if image selected isn't a directory
    if (isset($submittedValues['esi_icon_image']) && strrchr('REP_', (string) $submittedValues['esi_icon_image'])) {
        $submittedValues['esi_icon_image'] = null;
    }

    $bindParams = [];
    $rq = 'UPDATE extended_service_information SET ';
    $rq .= 'esi_notes = :esiNotes, ';
    $bindParams[':esiNotes'] = isset($submittedValues['esi_notes']) && $submittedValues['esi_notes'] != null
        ? $submittedValues['esi_notes'] : null;
    $rq .= 'esi_notes_url = :esiNotesUrl, ';
    $bindParams[':esiNotesUrl'] = isset($submittedValues['esi_notes_url']) && $submittedValues['esi_notes_url'] != null
        ? $submittedValues['esi_notes_url'] : null;
    $rq .= 'esi_action_url = :esiActionUrl, ';
    $bindParams[':esiActionUrl'] = isset($submittedValues['esi_action_url']) && $submittedValues['esi_action_url'] != null
        ? $submittedValues['esi_action_url'] : null;
    $rq .= 'esi_icon_image = :esiIconImage';
    $bindParams[':esiIconImage'] = isset($submittedValues['esi_icon_image']) && $submittedValues['esi_icon_image'] != null
        ? $submittedValues['esi_icon_image'] : null;

    if (! $isCloudPlatform) {
        $rq .= ', esi_icon_image_alt = :esiIconImageAlt, ';
        $bindParams[':esiIconImageAlt'] = isset($submittedValues['esi_icon_image_alt']) && $submittedValues['esi_icon_image_alt'] != null
            ? $submittedValues['esi_icon_image_alt'] : null;
        $rq .= 'graph_id = :graphId';
        $bindParams[':graphId'] = isset($submittedValues['graph_id']) && $submittedValues['graph_id'] != null
            ? $submittedValues['graph_id'] : null;
    }
    $rq .= ' WHERE service_service_id = :serviceServiceId';
    $bindParams[':serviceServiceId'] = (int) $serviceId;
    $statement = $pearDB->prepare($rq);
    foreach ($bindParams as $param => $paramValue) {
        $statement->bindValue($param, $paramValue);
    }
    $statement->execute();
}

function updateServiceExtInfos_MC($serviceId = null, $parameters = [])
{
    global $form, $pearDB, $isCloudPlatform;

    if (! $serviceId) {
        return;
    }

    $ret = count($parameters) ? $parameters : $form->getSubmitValues();
    $bindParams = [];
    $rq = 'UPDATE extended_service_information SET ';
    if (isset($ret['esi_notes']) && $ret['esi_notes'] != null) {
        $rq .= 'esi_notes = :esiNotes, ';
        $bindParams[':esiNotes'] = $ret['esi_notes'];
    }
    if (isset($ret['esi_notes_url']) && $ret['esi_notes_url'] != null) {
        $rq .= 'esi_notes_url = :esiNotesUrl, ';
        $bindParams[':esiNotesUrl'] = $ret['esi_notes_url'];
    }
    if (isset($ret['esi_action_url']) && $ret['esi_action_url'] != null) {
        $rq .= 'esi_action_url = :esiActionUrl, ';
        $bindParams[':esiActionUrl'] = $ret['esi_action_url'];
    }
    if (isset($ret['esi_icon_image']) && $ret['esi_icon_image'] != null) {
        $rq .= 'esi_icon_image = :esiIconImage, ';
        $bindParams[':esiIconImage'] = $ret['esi_icon_image'];
    }

    if (! $isCloudPlatform) {
        if (isset($ret['esi_icon_image_alt']) && $ret['esi_icon_image_alt'] != null) {
            $rq .= 'esi_icon_image_alt = :esiIconImageAlt, ';
            $bindParams[':esiIconImageAlt'] = $ret['esi_icon_image_alt'];
        }
        if (isset($ret['graph_id']) && $ret['graph_id'] != null) {
            $rq .= 'graph_id = :graphId, ';
            $bindParams[':graphId'] = $ret['graph_id'];
        }
    } else {
        $rq .= 'esi_icon_image_alt = NULL, graph_id = NULL, ';
    }

    if (strcmp('UPDATE extended_service_information SET ', $rq)) {
        // Delete last ',' in request
        $rq[strlen($rq) - 2] = ' ';
        $rq .= 'WHERE service_service_id = :serviceServiceId';
        $bindParams[':serviceServiceId'] = (int) $serviceId;
        $statement = $pearDB->prepare($rq);
        foreach ($bindParams as $param => $paramValue) {
            $statement->bindValue($param, $paramValue);
        }
        $statement->execute();
    }
}

function updateServiceTemplateUsed($useTpls = [])
{
    if (! count($useTpls)) {
        return;
    }
    global $pearDB;
    require_once './include/common/common-Func.php';
    foreach ($useTpls as $key => $value) {
        $query = 'UPDATE service SET service_template_model_stm_id = :templateId WHERE service_id = :serviceId';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':templateId', getMyServiceTPLID($value));
        $statement->bindValue(':serviceId', (int) $key, PDO::PARAM_INT);
        $statement->execute();
    }
}

function updateServiceCategories_MC($service_id = null, $ret = [])
{
    global $form, $pearDB;

    if (! $service_id) {
        return;
    }

    $ret = $ret['service_categories'] ?? $form->getSubmitValue('service_categories');
    if (is_array($ret)) {
        $insertStatement = $pearDB->prepare('INSERT INTO service_categories_relation (sc_id, service_service_id) VALUES (:scId, :serviceId)');
        $counter = count($ret);
        for ($i = 0; $i < $counter; $i++) {
            $insertStatement->bindValue(':scId', (int) $ret[$i], PDO::PARAM_INT);
            $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
            $insertStatement->execute();
        }
    }
}

function updateServiceCategories($service_id = null, $ret = [])
{
    global $form, $pearDB;
    if (! $service_id) {
        return;
    }

    $rq = 'DELETE FROM service_categories_relation
                    WHERE service_service_id = :service_id
                    AND NOT EXISTS(
                        SELECT sc_id
                        FROM service_categories sc
                        WHERE sc.sc_id = service_categories_relation.sc_id
                        AND sc.level IS NOT NULL
                    )';

    $statement = $pearDB->prepare($rq);
    $statement->bindValue(':service_id', (int) $service_id, PDO::PARAM_INT);
    $statement->execute();
    if (isset($ret['service_categories'])) {
        $ret = $ret['service_categories'];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'service_categories');
    }
    $insertStatement = $pearDB->prepare('INSERT INTO service_categories_relation (sc_id, service_service_id) VALUES (:scId, :serviceId)');
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $insertStatement->bindValue(':scId', (int) $ret[$i], PDO::PARAM_INT);
        $insertStatement->bindValue(':serviceId', (int) $service_id, PDO::PARAM_INT);
        $insertStatement->execute();
    }
}

/**
 * Inserts criticality relations
 *
 * @param int $serviceId
 * @param int $criticalityId
 * @return void
 */
function setServiceCriticality($serviceId, $criticalityId)
{
    global $pearDB;

    $statement = $pearDB->prepare(
        'DELETE FROM service_categories_relation
                WHERE service_service_id =:service_service_id
                AND NOT EXISTS(
                    SELECT sc_id
                    FROM service_categories sc
                    WHERE sc.sc_id = service_categories_relation.sc_id
                    AND sc.level IS NULL)'
    );
    $statement->bindValue(':service_service_id', $serviceId, PDO::PARAM_INT);
    $statement->execute();
    if ($criticalityId) {
        $statement = $pearDB->prepare(
            'INSERT INTO service_categories_relation (sc_id, service_service_id)
                                VALUES (:sc_id,:service_service_id)'
        );
        $statement->bindValue(':sc_id', $criticalityId, PDO::PARAM_INT);
        $statement->bindValue(':service_service_id', $serviceId, PDO::PARAM_INT);
        $statement->execute();
    }
}

/**
 * Rule for test if a ldap contactgroup name already exists
 *
 * @param array $listCgs The list of contactgroups to validate
 * @param mixed $list
 * @return bool
 */
function testCg2($list)
{
    return CentreonContactgroup::verifiedExists($list);
}

/**
 * @param int $serviceId
 * @return int[]
 */
function getPollersForConfigChangeFlagFromServiceId(int $serviceId): array
{
    $hostIds = findHostsForConfigChangeFlagFromServiceIds([$serviceId]);

    return findPollersForConfigChangeFlagFromHostIds($hostIds);
}

/**
 * Find all the host IDs for which the service is bound
 *
 * @param int $serviceId
 * @return int[]
 */
function findHostsOfService(int $serviceId): array
{
    global $pearDB;
    $statement = $pearDB->prepare(
        'SELECT host.host_id
        FROM host
        INNER JOIN host_service_relation hsr
          ON hsr.host_host_id = host.host_id
        WHERE hsr.service_service_id = :service_id'
    );
    $statement->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
    $statement->execute();
    $hostIds = [];
    while (($hostId = $statement->fetchColumn(0)) !== false) {
        $hostIds[] = $hostId;
    }

    return $hostIds;
}

/**
 * Will check if the service template inherited by the service has a command.
 *
 * @param array<string, mixed> $fields The fields of the service
 *
 * @return array<string, string>|bool
 */
function checkServiceTemplateHasCommand(array $fields): array|bool
{
    $errors['command_command_id'] = _(
        'The selected inherited service template does not contain any check command. You must select one here.'
    );
    if (! empty($fields['command_command_id'])) {
        return true;
    }

    if (! isset($fields['service_template_model_stm_id']) && empty($fields['command_command_id'])) {
        return $errors;
    }

    return isCheckCommandDefined($fields['service_template_model_stm_id']) ? true : $errors;
}

function isCheckCommandDefined(int $serviceId): bool
{
    global $pearDB;
    $result = $pearDB->fetchAssociative(
        'SELECT command_command_id, service_template_model_stm_id FROM service WHERE service_id = :stm_id',
        QueryParameters::create([QueryParameter::int('stm_id', $serviceId)])
    );

    if ($result['command_command_id'] !== null) {
        return true;
    }
    if ($result['command_command_id'] === null && $result['service_template_model_stm_id'] !== null) {
        return isCheckCommandDefined($result['service_template_model_stm_id']);
    }

    return false;
}

// ------ API Configuration calls --------------------------------------------------------

/**
 * Inserts a new service template into the database.
 *
 * @param array $submittedValues
 *
 * @throws Exception
 * @throws LogicException
 * @throws TransportExceptionInterface
 * @throws RedirectionExceptionInterface
 * @throws ClientExceptionInterface
 * @throws ServerExceptionInterface
 *
 * @return int
 */
function insertServiceTemplate(array $submittedValues = []): int
{
    global $isCloudPlatform;

    return $isCloudPlatform
        ? insertServiceTemplateForCloud($submittedValues)
        : insertServiceTemplateForOnPremise($submittedValues);
}

/**
 * Inserts a new service template into the database for cloud.
 *
 * @param array $submittedValues
 *
 * @throws Exception
 * @throws LogicException
 * @throws TransportExceptionInterface
 * @throws RedirectionExceptionInterface
 * @throws ClientExceptionInterface
 * @throws ServerExceptionInterface
 *
 * @return int
 */
function insertServiceTemplateForCloud(array $submittedValues): int
{
    global $centreon, $basePath;

    try {
        $serviceId = insertServiceTemplateByApi(
            submittedValues: $submittedValues,
            isCloudPlatform: true,
            basePath: $basePath
        );

        updateServiceHost($serviceId, $submittedValues);
        updateServiceServiceGroup($serviceId, $submittedValues);
        signalConfigurationChange('service', $serviceId);
        $centreon->user->access->updateACL(
            [
                'type' => 'SERVICE',
                'id' => $serviceId,
                'action' => 'ADD',
            ]
        );

        return $serviceId;
    } catch (Exception $exception) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while creating service template: {$exception->getMessage()}",
            customContext: ['service template Id' => $serviceId, 'basePath' => $basePath],
            exception: $exception
        );

        throw $exception;
    }
}

/**
 * Inserts a new service template into the database for on-premise.
 *
 * @param array $submittedValues
 *
 * @throws Exception
 * @throws LogicException
 * @throws CentreonDbException
 * @throws PDOException
 * @throws TransportExceptionInterface
 * @throws RedirectionExceptionInterface
 * @throws ClientExceptionInterface
 * @throws ServerExceptionInterface
 *
 * @return int
 */
function insertServiceTemplateForOnPremise(array $submittedValues = []): int
{
    global $centreon, $basePath;

    try {
        $serviceId = insertServiceTemplateByApi(
            submittedValues: $submittedValues,
            isCloudPlatform: false,
            basePath: $basePath
        );

        insertServiceTemplateAdditionalOptions($serviceId, $submittedValues);
        updateServiceContactGroup($serviceId, $submittedValues);
        updateServiceContact($serviceId, $submittedValues);
        updateServiceNotifs($serviceId, $submittedValues);
        updateServiceHost($serviceId, $submittedValues);
        updateServiceServiceGroup($serviceId, $submittedValues);
        updateServiceTrap($serviceId, $submittedValues);
        signalConfigurationChange('service', $serviceId);
        $centreon->user->access->updateACL(
            [
                'type' => 'SERVICE',
                'id' => $serviceId,
                'action' => 'ADD',
            ]
        );

        return $serviceId;
    } catch (Exception $exception) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while creating service template: {$exception->getMessage()}",
            customContext: ['service template Id' => $serviceId ?? null, 'basePath' => $basePath],
            exception: $exception
        );

        throw $exception;
    }
}

/**
 * Inserts a new service template into the database by calling the API.
 *
 * @param array $submittedValues
 * @param bool $isCloudPlatform
 * @param string $basePath
 *
 * @throws Exception
 * @throws LogicException
 * @throws PDOException
 * @throws TransportExceptionInterface
 * @throws RedirectionExceptionInterface
 * @throws ClientExceptionInterface
 * @throws ServerExceptionInterface
 *
 * @return int
 */
function insertServiceTemplateByApi(
    array $submittedValues = [],
    bool $isCloudPlatform = false,
    string $basePath,
): int {
    $kernel = Kernel::createForWeb();
    $router = $kernel->getContainer()->get(Router::class) ?? throw new LogicException('Router not found');

    if ($isCloudPlatform) {
        $payload = getServiceTemplatePayload($submittedValues, true);
    } else {
        $payload = getServiceTemplatePayload($submittedValues);
    }

    $url = $router->generate(
        'AddServiceTemplate',
        $basePath ? ['base_uri' => $basePath] : [],
        UrlGeneratorInterface::ABSOLUTE_URL
    );

    $response = callApi($url, 'POST', $payload);
    if ($response['status_code'] !== 201) {
        throw new Exception($response['content']['message'] ?? 'Unexpected return code by API');
    }

    $serviceId = $response['content']['id'] ?? null;
    if ($serviceId === null) {
        throw new Exception('Failed to create service template by API');
    }

    return $serviceId;
}

/**
 * Constructs the payload for a service template.
 *
 * @param array $submittedValues
 * @param bool $isCloudPlatform
 *
 * @throws PDOException
 *
 * @return array
 */
function getServiceTemplatePayload(
    array $submittedValues,
    bool $isCloudPlatform = false,
): array {
    global $form, $pearDB;

    $service = new CentreonService($pearDB);

    $name = '';
    if (isset($submittedValues['service_description'])) {
        $name = preg_replace('/\s{2,}/', ' ', $service->checkIllegalChar($submittedValues['service_description']));
    }

    if (! $isCloudPlatform && isset($submittedValues['command_command_id_arg2'])) {
        $submittedValues['command_command_id_arg2'] = str_replace(
            ['\n', '\t', '\r'],
            ['//BR//', '//T//', '//R//'],
            $submittedValues['command_command_id_arg2']
        );
    }

    $submittedValues['command_command_id_arg'] = getCommandArgs($_POST, $submittedValues);

    if (isset($submittedValues['esi_icon_image']) && strrchr('REP_', (string) $submittedValues['esi_icon_image'])) {
        $submittedValues['esi_icon_image'] = null;
    }

    $payload = [
        'service_template_id' => $submittedValues['service_template_model_stm_id']
            && is_numeric($submittedValues['service_template_model_stm_id'])
            && (int) $submittedValues['service_template_model_stm_id'] > 0
                ? (int) $submittedValues['service_template_model_stm_id']
                : null,
        'check_command_id' => $submittedValues['command_command_id']
            && is_numeric($submittedValues['command_command_id'])
            && (int) $submittedValues['command_command_id'] > 0
                ? (int) $submittedValues['command_command_id']
                : null,
        'check_timeperiod_id' => $submittedValues['timeperiod_tp_id']
            && is_numeric($submittedValues['timeperiod_tp_id'])
            && (int) $submittedValues['timeperiod_tp_id'] > 0
                ? (int) $submittedValues['timeperiod_tp_id']
                : null,
        'event_handler_command_id' => $submittedValues['command_command_id2']
            && is_numeric($submittedValues['command_command_id2'])
            && (int) $submittedValues['command_command_id2'] > 0
                ? (int) $submittedValues['command_command_id2']
                : null,
        'name' => $name,
        'alias' => $submittedValues['service_alias'] ?? null,
        'max_check_attempts' => $submittedValues['service_max_check_attempts']
            ? (int) $submittedValues['service_max_check_attempts']
            : null,
        'normal_check_interval' => $submittedValues['service_normal_check_interval']
            ? (int) $submittedValues['service_normal_check_interval']
            : null,
        'retry_check_interval' => $submittedValues['service_retry_check_interval']
            ? (int) $submittedValues['service_retry_check_interval'] : null,
        'event_handler_enabled' => isset($submittedValues['service_event_handler_enabled']['service_event_handler_enabled'])
            && $submittedValues['service_event_handler_enabled']['service_event_handler_enabled'] != 2
                ? (int) $submittedValues['service_event_handler_enabled']['service_event_handler_enabled']
                : 2,
        'check_command_args' => ! empty($submittedValues['command_command_id_arg'])
            ? array_values(array_filter(
                explode('!', $submittedValues['command_command_id_arg']),
                function ($value) {
                    return $value !== '';
                }
            ))
            : [],
        'severity_id' => $submittedValues['criticality_id']
            && is_numeric($submittedValues['criticality_id'])
            && (int) $submittedValues['criticality_id'] > 0
                ? (int) $submittedValues['criticality_id']
                : null,
        'action_url' => ! empty($submittedValues['esi_action_url']) ? $submittedValues['esi_action_url'] : null,
        'icon_id' => $submittedValues['esi_icon_image']
            && is_numeric($submittedValues['esi_icon_image'])
            && (int) $submittedValues['esi_icon_image'] > 0
                ? (int) $submittedValues['esi_icon_image']
                : null,
        'note' => ! empty($submittedValues['esi_notes']) ? $submittedValues['esi_notes'] : null,
        'note_url' => ! empty($submittedValues['esi_notes_url']) ? $submittedValues['esi_notes_url'] : null,
        'service_categories' => isset($submittedValues['service_categories'])
            ? array_map('intval', $submittedValues['service_categories'])
            : CentreonUtils::mergeWithInitialValues($form, 'service_categories'),
    ];

    if (! $isCloudPlatform) {
        $additionalFields = [
            'notification_timeperiod_id' => $submittedValues['timeperiod_tp_id2']
                && is_numeric($submittedValues['timeperiod_tp_id2'])
                && (int) $submittedValues['timeperiod_tp_id2'] > 0
                    ? (int) $submittedValues['timeperiod_tp_id2']
                    : null,
            'volatility_enabled' => isset($submittedValues['service_is_volatile']['service_is_volatile'])
                && $submittedValues['service_is_volatile']['service_is_volatile'] != 2
                    ? (int) $submittedValues['service_is_volatile']['service_is_volatile']
                    : 2,
            'active_check_enabled' => isset($submittedValues['service_active_checks_enabled']['service_active_checks_enabled'])
                && $submittedValues['service_active_checks_enabled']['service_active_checks_enabled'] != 2
                    ? (int) $submittedValues['service_active_checks_enabled']['service_active_checks_enabled']
                    : 2,
            'passive_check_enabled' => isset($submittedValues['service_passive_checks_enabled']['service_passive_checks_enabled'])
                && $submittedValues['service_passive_checks_enabled']['service_passive_checks_enabled'] != 2
                    ? (int) $submittedValues['service_passive_checks_enabled']['service_passive_checks_enabled']
                    : 2,
            'freshness_checked' => isset($submittedValues['service_check_freshness']['service_check_freshness'])
                && $submittedValues['service_check_freshness']['service_check_freshness'] != 2
                    ? (int) $submittedValues['service_check_freshness']['service_check_freshness']
                    : 2,
            'freshness_threshold' => $submittedValues['service_freshness_threshold']
                ? (int) $submittedValues['service_freshness_threshold']
                : null,
            'low_flap_threshold' => $submittedValues['service_low_flap_threshold']
                ? (int) $submittedValues['service_low_flap_threshold']
                : null,
            'high_flap_threshold' => $submittedValues['service_high_flap_threshold']
                ? (int) $submittedValues['service_high_flap_threshold']
                : null,
            'flap_detection_enabled' => isset($submittedValues['service_flap_detection_enabled']['service_flap_detection_enabled'])
                && $submittedValues['service_flap_detection_enabled']['service_flap_detection_enabled'] != 2
                    ? (int) $submittedValues['service_flap_detection_enabled']['service_flap_detection_enabled']
                    : 2,
            'notification_interval' => $submittedValues['service_notification_interval']
                ? (int) $submittedValues['service_notification_interval']
                : null,
            'notification_enabled' => isset($submittedValues['service_notifications_enabled']['service_notifications_enabled'])
                && $submittedValues['service_notifications_enabled']['service_notifications_enabled'] != 2
                    ? (int) $submittedValues['service_notifications_enabled']['service_notifications_enabled']
                    : 2,
            'is_contact_additive_inheritance' => isset($submittedValues['contact_additive_inheritance']) ? true : false,
            'is_contact_group_additive_inheritance' => isset($submittedValues['cg_additive_inheritance']) ? true : false,
            'first_notification_delay' => $submittedValues['service_first_notification_delay']
                ? (int) $submittedValues['service_first_notification_delay']
                : null,
            'recovery_notification_delay' => $submittedValues['service_recovery_notification_delay']
                ? (int) $submittedValues['service_recovery_notification_delay']
                : null,
            'comment' => ! empty($submittedValues['service_comment']) ? $submittedValues['service_comment'] : null,
            'event_handler_command_args' => ! empty($submittedValues['command_command_id_arg2'])
                ? array_values(array_filter(
                    explode('!', $submittedValues['command_command_id_arg2']),
                    function ($value) {
                        return $value !== '';
                    }
                ))
                : [],
            'acknowledgement_timeout' => $submittedValues['service_acknowledgement_timeout']
                ? (int) $submittedValues['service_acknowledgement_timeout']
                : null,
            'icon_alternative' => ! empty($submittedValues['esi_icon_image_alt'])
                ? $submittedValues['esi_icon_image_alt']
                : null,
            'graph_template_id' => $submittedValues['graph_id']
                && is_numeric($submittedValues['graph_id'])
                && (int) $submittedValues['graph_id'] > 0
                    ? (int) $submittedValues['graph_id']
                    : null,
        ];

        $payload = array_merge($payload, $additionalFields);
    }

    if (isset($submittedValues['macroInput'], $submittedValues['macroValue'])) {
        $macroDescription = [];
        foreach ($submittedValues as $name => $value) {
            if (preg_match_all("/^macroDescription_(\w+)$/", $name, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $macroDescription[$match[1]] = $value;
                }
            }
        }

        foreach ($submittedValues['macroInput'] as $key => $macroName) {
            $payload['macros'][] = [
                'id' => (empty((int) $submittedValues['macroId'][$key]) ? null : (int) $submittedValues['macroId'][$key]),
                'name' => $macroName,
                'value' => $submittedValues['macroValue'][$key] === PASSWORD_REPLACEMENT_VALUE
                    ? null
                    : (
                        str_starts_with($submittedValues['macroValue'][$key], VaultConfiguration::VAULT_PATH_PATTERN)
                        ? null
                        : $submittedValues['macroValue'][$key]
                    ),
                'is_password' => isset($submittedValues['macroPassword'][$key]) ? true : false,
                'description' => $macroDescription[$key] ?? null,
            ];
        }
    }

    return $payload;
}

/**
 * @param array<int, int> $services The list of service IDs to delete (Ids are the keys)
 *
 * @throws Exception
 */
function deleteServiceByApi(array $services = []): void
{
    global $basePath;

    $serviceIds = array_keys($services);
    if ($serviceIds === []) {
        return;
    }

    $kernel = Kernel::createForWeb();
    $router = $kernel->getContainer()->get(Router::class)
        ?? throw new LogicException('Router not found in container');
    $servicesWithError = [];
    foreach ($serviceIds as $serviceId) {
        $url = $router->generate(
            'DeleteService',
            ['base_uri' => $basePath, 'serviceId' => $serviceId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $response = callApi($url, 'DELETE', []);
        if ($response['status_code'] !== 204) {
            $servicesWithError[] = [
                'service_id' => $serviceId,
                'message' => $response['content'] !== null
                    ? json_encode($response['content'])
                    : null,
            ];
        }
    }

    if ($servicesWithError !== []) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while deleting services',
            ['service_ids' => $servicesWithError]
        );
    }
}

/**
 * @param array<int, array> $serviceTemplates The list of service templates to delete (Ids are the keys)
 *
 * @throws Exception
 */
function deleteServiceTemplateByApi(array $serviceTemplates = []): void
{
    global $basePath;

    $serviceTemplateIds = array_keys($serviceTemplates);
    if ($serviceTemplateIds === []) {
        return;
    }

    $kernel = Kernel::createForWeb();
    $router = $kernel->getContainer()->get(Router::class)
        ?? throw new LogicException('Router not found in container');
    $serviceTemplatesWithError = [];
    foreach ($serviceTemplateIds as $serviceTemplateId) {
        $url = $router->generate(
            'DeleteServiceTemplate',
            ['base_uri' => $basePath, 'serviceTemplateId' => $serviceTemplateId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $response = callApi($url, 'DELETE', []);
        if ($response['status_code'] !== 204) {
            $serviceTemplatesWithError[] = [
                'service_template_id' => $serviceTemplateId,
                'message' => $response['content'] !== null
                    ? json_encode($response['content'])
                    : null,
            ];
        }
    }

    if ($serviceTemplatesWithError !== []) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while deleting service templates',
            ['service_ids' => $serviceTemplatesWithError]
        );
    }
}

/**
 * @param string $url
 * @param string $httpMethod
 * @param array<string, mixed> $payload
 *
 * @throws TransportExceptionInterface
 * @throws RedirectionExceptionInterface
 * @throws ClientExceptionInterface
 * @throws ServerExceptionInterface
 *
 * @return array{status_code: int, content: null|array} return the status code of the request and its content
 */
function callApi(string $url, string $httpMethod, array $payload): array
{
    $kernel = Kernel::createForWeb();

    /** @var ServiceLocator $serviceLocator */
    $serviceLocator = $kernel->getContainer()->get('legacy.service_locator');

    if (! $serviceLocator->has('internal_api_client')) {
        throw new RuntimeException('internal_api_client service is not registered in the service locator');
    }

    /** @var InternalApiClient $client */
    $client = $serviceLocator->get('internal_api_client');

    return $client->request($url, $httpMethod, CentreonSession::resolveSessionCookie(), $payload);
}
