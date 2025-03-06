<?php declare(strict_types=1);

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

if (! isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/include/common/vault-functions.php';

use App\Kernel;
use Centreon\Domain\Log\Logger;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
/**
 * Indicates if the resource name has already been used.
 *
 * @global CentreonDB $pearDB
 * @global HTML_QuickFormCustom $form
 *
 * @param string $name
 * @param int $instanceId
 *
 * @return bool Return false if the resource name has already been used
 */
function testExistence($name = null, $instanceId = null)
{
    global $pearDB, $form;

    $id = 0;
    $instanceIds = [];
    if (isset($form)) {
        $id = (int) $form->getSubmitValue('resource_id');
        $instanceIds = $form->getSubmitValue('instance_id');
        $instanceIds = filter_var_array(
            $instanceIds,
            FILTER_VALIDATE_INT
        );
        if (in_array(false, $instanceIds, true)) {
            return true;
        }
    } elseif (! is_null($instanceId) && $instanceId) {
        $instanceIds = [(int) $instanceId];
    }
    if ($instanceIds === []) {
        return true;
    }
    $prepare = $pearDB->prepare(
        'SELECT cr.resource_name, crir.resource_id, crir.instance_id '
        . 'FROM cfg_resource cr, cfg_resource_instance_relations crir '
        . 'WHERE cr.resource_id = crir.resource_id '
        . 'AND crir.instance_id IN (' . implode(',', $instanceIds) . ') '
        . 'AND cr.resource_name = :resource_name'
    );
    $prepare->bindValue(':resource_name', $name, PDO::PARAM_STR);
    $prepare->execute();
    $total = $prepare->rowCount();
    $result = $prepare->fetch(PDO::FETCH_ASSOC);
    if ($total >= 1 && $result['resource_id'] === $id) {
        /**
         * In case of modification.
         */
        return true;
    }

    return ! ($total >= 1 && $result['resource_id'] !== $id);
        /**
         * In case of duplicate.
         */
}

/**
 * Deletes resources.
 *
 * @global CentreonDB $pearDB
 *
 * @param int[] $resourceIds Resource ids to delete
 */
function deleteResourceInDB($resourceIds = []): void
{
    global $pearDB;

    foreach (array_keys($resourceIds) as $currentResourceId) {
        if (is_int($currentResourceId)) {
            $statement = $pearDB->prepare(
                'SELECT *FROM cfg_resource WHERE resource_id = :resourceId'
            );
            $statement->bindValue(':resourceId', $currentResourceId);
            $statement->execute();

            if (false !== $data = $statement->fetch()) {
                deleteFromVault($data);

                $pearDB->query(
                    "DELETE FROM cfg_resource WHERE resource_id = {$currentResourceId}"
                );
            }
        }
    }
}

/**
 * Enables a resource.
 *
 * @global CentreonDB $pearDB
 *
 * @param int[] $resourceId Resource id to enable
 */
function enableResourceInDB($resourceId): void
{
    global $pearDB;

    if (is_int($resourceId)) {
        $pearDB->query(
            "UPDATE cfg_resource SET resource_activate = '1' "
            . "WHERE resource_id = {$resourceId}"
        );
    }
}

/**
 * Disables a resource.
 *
 * @global CentreonDB $pearDB
 *
 * @param int $resourceId Resource id to disable
 */
function disableResourceInDB($resourceId): void
{
    global $pearDB;
    if (is_int($resourceId)) {
        $pearDB->query(
            "UPDATE cfg_resource SET resource_activate = '0' "
            . "WHERE resource_id = {$resourceId}"
        );
    }
}
/**
 * Duplicates resource.
 *
 * @global CentreonDB $pearDB
 *
 * @param array $resourceIds List of resource id to duplicate
 * @param int[] $nbrDup Number of copy
 */
function multipleResourceInDB($resourceIds = [], $nbrDup = []): void
{
    global $pearDB;

    foreach (array_keys($resourceIds) as $resourceId) {
        if (is_int($resourceId)) {
            $dbResult = $pearDB->query("SELECT * FROM cfg_resource WHERE resource_id = {$resourceId} LIMIT 1");
            /**
             * @var array{
             *  resource_id:int,
             *  resource_name:string,
             *  resource_line:string,
             *  resource_comment:string,
             *  resource_activate:string,
             *  is_password:int
             * } $resourceConfiguration
             */
            $resourceConfiguration = $dbResult->fetch();

            for ($newIndex = 1; $newIndex <= $nbrDup[$resourceId]; $newIndex++) {
                $name = $resourceConfiguration['resource_name'] . '_' . $newIndex;
                $value = $resourceConfiguration['resource_line'];
                if (
                    (bool) $resourceConfiguration['is_password'] === true
                    && str_starts_with($resourceConfiguration['resource_line'], VaultConfiguration::VAULT_PATH_PATTERN)
                ) {
                    $resourcesFromVault =  getFromVault($resourceConfiguration['resource_line']);
                    $value = $resourcesFromVault[
                        str_replace('$', '', $resourceConfiguration['resource_name'])
                    ];
                }

                if (testExistence($name) && ! is_null($value)) {
                    $vaultPath = saveInVault($name, $value);
                    $value = $vaultPath ?? $value;

                    $statement = $pearDB->prepare(
                        <<<'SQL'
                            INSERT INTO cfg_resource
                            (resource_id, resource_name, resource_line, resource_comment, resource_activate, is_password)
                            VALUES (NULL, :name, :value, :comment, :is_active, :is_password)
                            SQL
                    );
                    $statement->bindValue(':name', $name, PDO::PARAM_STR);
                    $statement->bindValue(':value', $value, PDO::PARAM_STR);
                    $statement->bindValue(':comment', $resourceConfiguration['resource_comment'], PDO::PARAM_STR);
                    $statement->bindValue(':is_active', $resourceConfiguration['resource_activate'], PDO::PARAM_STR);
                    $statement->bindValue(':is_password', $resourceConfiguration['is_password'], PDO::PARAM_INT);
                    $statement->execute();

                    $lastId = $pearDB->lastInsertId();
                    $pearDB->query(
                        'INSERT INTO cfg_resource_instance_relations ('
                        . "SELECT {$lastId}, instance_id "
                        . 'FROM cfg_resource_instance_relations '
                        . "WHERE resource_id = {$resourceId})"
                    );
                }
            }
        }
    }
}

function updateResourceInDB($resource_id = null): void
{
    if (! $resource_id) {
        return;
    }
    updateResource((int) $resource_id);
    insertInstanceRelations((int) $resource_id);
}

/**
 * Updates a resource which is in the form.
 *
 * @global HTML_QuickFormCustom $form
 * @global CentreonDB $pearDB
 * @global Centreon $centreon
 *
 * @param int $resourceId
 */
function updateResource($resourceId): void
{
    global $form, $pearDB, $centreon;

    if (is_null($resourceId)) {
        return;
    }

    $submitedValues = $form->getSubmitValues();

    $isActivate = false;
    if (
        isset($submitedValues['resource_activate'], $submitedValues['resource_activate']['resource_activate'])

        && $submitedValues['resource_activate']['resource_activate'] === '1'
    ) {
        $isActivate = true;
    }

    if ($_REQUEST['is_password'] && ! str_starts_with($_REQUEST['resource_line'], VaultConfiguration::VAULT_PATH_PATTERN)) {
        $vaultPath = saveInVault($_REQUEST['resource_name'], $_REQUEST['resource_line']);
        $_REQUEST['resource_line'] = $vaultPath ?? $_REQUEST['resource_line'];
    }

    $prepare = $pearDB->prepare(
        <<<'SQL'
            UPDATE cfg_resource
            SET resource_name = :resource_name, resource_line = :resource_line,
                resource_comment= :resource_comment, resource_activate= :is_activate,
                is_password = :is_password
            WHERE resource_id = :resource_id
            SQL
    );

    $prepare->bindValue(
        ':resource_name',
        $pearDB->escape($submitedValues['resource_name']),
        PDO::PARAM_STR
    );

    $prepare->bindValue(
        ':resource_line',
        $pearDB->escape($_REQUEST['resource_line']),
        PDO::PARAM_STR
    );

    $prepare->bindValue(
        ':resource_comment',
        $pearDB->escape($submitedValues['resource_comment']),
        PDO::PARAM_STR
    );

    $prepare->bindValue(
        ':is_activate',
        ($isActivate ? '1' : '0'),
        PDO::PARAM_STR
    );

    $prepare->bindValue(':resource_id', $resourceId, PDO::PARAM_INT);
    $prepare->bindValue(':is_password', (int) $_REQUEST['is_password'], PDO::PARAM_INT);
    $prepare->execute();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($submitedValues);
    $centreon->CentreonLogAction->insertLog(
        'resource',
        $resourceId,
        CentreonDB::escape($submitedValues['resource_name']),
        'c',
        $fields
    );
}

function insertResourceInDB()
{
    $resource_id = insertResource();
    insertInstanceRelations($resource_id);

    return $resource_id;
}

function insertResource($ret = [])
{
    global $form, $pearDB, $centreon;

    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }

    if ($ret['is_password']) {
        $vaultPath = saveInVault($ret['resource_name'], $ret['resource_line']);
        $ret['resource_line'] = $vaultPath ?? $ret['resource_line'];
    }

    $statement = $pearDB->prepare(
        'INSERT INTO cfg_resource
        (resource_name, resource_line, resource_comment, resource_activate, is_password)
        VALUES (:name, :line, :comment, :is_activated, :is_password)'
    );
    $statement->bindValue(
        ':name',
        ! empty($ret['resource_name'])
            ? $ret['resource_name']
            : null
    );
    $statement->bindValue(
        ':line',
        ! empty($ret['resource_line'])
            ? $ret['resource_line']
            : null
    );
    $statement->bindValue(
        ':comment',
        ! empty($ret['resource_comment'])
            ? $ret['resource_comment']
            : null
    );
    $isActivated = isset($ret['resource_activate']['resource_activate'])
        && (bool) (int) $ret['resource_activate']['resource_activate'];
    $statement->bindValue(':is_activated', (string) (int) $isActivated);
    $statement->bindValue('is_password', (int) $ret['is_password'], PDO::PARAM_INT);
    $statement->execute();

    $dbResult = $pearDB->query('SELECT MAX(resource_id) FROM cfg_resource');
    $resource_id = $dbResult->fetch();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        'resource',
        $resource_id['MAX(resource_id)'],
        CentreonDB::escape($ret['resource_name']),
        'a',
        $fields
    );

    return $resource_id['MAX(resource_id)'];
}

function insertInstanceRelations($resourceId, $instanceId = null): void
{
    if (is_numeric($resourceId)) {
        global $pearDB;
        $pearDB->query('DELETE FROM cfg_resource_instance_relations WHERE resource_id = ' . (int) $resourceId);

        if (! is_null($instanceId)) {
            $instances = [$instanceId];
        } else {
            global $form;
            $instances = CentreonUtils::mergeWithInitialValues($form, 'instance_id');
        }

        $subQuery = '';
        foreach ($instances as $instanceId) {
            if (is_numeric($instanceId)) {
                if (! empty($subQuery)) {
                    $subQuery .= ', ';
                }
                $subQuery .= '(' . (int) $resourceId . ', ' . (int) $instanceId . ')';
            }
        }
        if (! empty($subQuery)) {
            $pearDB->query(
                'INSERT INTO cfg_resource_instance_relations (resource_id, instance_id) VALUES ' . $subQuery
            );
        }
    }
}

function getLinkedPollerList($resource_id)
{
    global $pearDB;

    $str = '';
    $query = 'SELECT ns.name, ns.id FROM cfg_resource_instance_relations nsr, cfg_resource r, nagios_server ns '
        . "WHERE nsr.resource_id = r.resource_id AND nsr.instance_id = ns.id AND nsr.resource_id = '"
        . $resource_id . "'";
    $dbResult = $pearDB->query($query);
    while ($data = $dbResult->fetch()) {
        $str .= "<a href='main.php?p=60901&o=c&server_id=" . $data['id'] . "'>" . HtmlSanitizer::createFromString($data['name'])->sanitize()->getString() . '</a> ';
    }
    unset($dbResult);

    return $str;
}

/**
 * @param string $vaultPath
 *
 * @return array<string,string>
 */
function getFromVault(string $vaultPath): array
{
    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    /** @var ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository */
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    if ($vaultConfiguration !== null) {
        /**@var ReadVaultRepositoryInterface $readVaultRepository */
        $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
        try {
            return readPollerMacroSecretsInVault(
                readVaultRepository: $readVaultRepository,
                vaultPath:  $vaultPath
            );
        } catch (Throwable $ex) {
            $logger->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            error_log((string) $ex);
        }
    }

    return [];
}

function saveInVault(string $key, string $value): ?string {
    global $pearDB;

    $kernel = Kernel::createForWeb();
    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);
    /** @var ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository */
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();
    if ($vaultConfiguration !== null) {
        /**@var ReadVaultRepositoryInterface $readVaultRepository */
        $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
        /**@var WriteVaultRepositoryInterface $writeVaultRepository */
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
        $writeVaultRepository->setCustomPath(AbstractVaultRepository::POLLER_MACRO_VAULT_PATH);
        try {
            return upsertPollerMacroSecretInVault(
                $readVaultRepository,
                $writeVaultRepository,
                $key,
                $value,
                retrievePollerMacroVaultPathFromDatabase($pearDB)
            );
        } catch (Throwable $ex) {
            $logger->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            error_log((string) $ex);
        }
    }

    return null;
}

/**
 * @param array{resource_line:string,resource_name:string} $data
 */
function deleteFromVault(array $data): void {
    if (str_starts_with($data['resource_line'], VaultConfiguration::VAULT_PATH_PATTERN)) {
        $uuid = preg_match(
                '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                $data['resource_line'],
                $matches
            )
            && isset($matches[2]) ? $matches[2] : null;

        $kernel = Kernel::createForWeb();
        /** @var Logger $logger */
        $logger = $kernel->getContainer()->get(Logger::class);
        /** @var ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository */
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            ReadVaultConfigurationRepositoryInterface::class
        );

        $vaultConfiguration = $readVaultConfigurationRepository->find();
        if ($vaultConfiguration !== null) {
            /**@var ReadVaultRepositoryInterface $readVaultRepository */
            $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
            /**@var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::POLLER_MACRO_VAULT_PATH);
            try {
                deletePollerMacroSecretInVault(
                    $readVaultRepository,
                    $writeVaultRepository,
                    $uuid,
                    $data['resource_line'],
                    $data['resource_name'],
                );
            } catch (Throwable $ex) {
                $logger->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
                error_log((string) $ex);
            }
        }
    }
}
