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

use App\Kernel;

if (!isset($centreon)) {
    exit();
}

use Core\ActionLog\Domain\Model\ActionLog;
use Core\Infrastructure\Common\Api\Router;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

function includeExcludeTimeperiods($tpId, $includeTab = [], $excludeTab = [])
{
    global $pearDB;

    /*
     * Insert inclusions
     */
    if (isset($includeTab) && is_array($includeTab)) {
        $str = "";
        foreach ($includeTab as $tpIncludeId) {
            if ($str != "") {
                $str .= ", ";
            }
            $str .= "('" . $tpId . "', '" . $tpIncludeId . "')";
        }
        if (strlen($str)) {
            $query = "INSERT INTO timeperiod_include_relations (timeperiod_id, timeperiod_include_id ) VALUES " . $str;
            $pearDB->query($query);
        }
    }

    /*
     * Insert exclusions
     */
    if (isset($excludeTab) && is_array($excludeTab)) {
        $str = "";
        foreach ($excludeTab as $tpExcludeId) {
            if ($str != "") {
                $str .= ", ";
            }
            $str .= "('" . $tpId . "', '" . $tpExcludeId . "')";
        }
        if (strlen($str)) {
            $query = "INSERT INTO timeperiod_exclude_relations (timeperiod_id, timeperiod_exclude_id ) VALUES " . $str;
            $pearDB->query($query);
        }
    }
}

function testTPExistence($name = null)
{
    global $pearDB, $form, $centreon;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('tp_id');
    }

    $query = 'SELECT tp_name, tp_id FROM timeperiod WHERE tp_name = :tp_name';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(
        ':tp_name',
        htmlentities($centreon->checkIllegalChar($name), ENT_QUOTES, "UTF-8"),
        \PDO::PARAM_STR
    );
    $statement->execute();
    $tp = $statement->fetch(\PDO::FETCH_ASSOC);
    #Modif case
    if ($statement->rowCount() >= 1 && $tp["tp_id"] == $id) {
        return true;
    } elseif ($statement->rowCount() >= 1 && $tp["tp_id"] != $id) { #Duplicate entry
        return false;
    } else {
        return true;
    }
}

function multipleTimeperiodInDB($timeperiods = [], $nbrDup = [])
{
    global $centreon;

    foreach ($timeperiods as $key => $value) {
        global $pearDB;

        $fields = [];
        $dbResult = $pearDB->query("SELECT * FROM timeperiod WHERE tp_id = '" . $key . "' LIMIT 1");

        $query = "SELECT days, timerange FROM timeperiod_exceptions WHERE timeperiod_id = '" . $key . "'";
        $res = $pearDB->query($query);
        while ($row = $res->fetch()) {
            foreach ($row as $keyz => $valz) {
                $fields[$keyz] = $valz;
            }
        }

        $row = $dbResult->fetch();
        $row["tp_id"] = null;
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = [];
            foreach ($row as $key2 => $value2) {
                if ($key2 == "tp_name") {
                    $value2 .= "_" . $i;
                }
                if ($key2 == "tp_name") {
                    $tp_name = $value2;
                }
                $val[] = $value2 ?: null;
                if ($key2 != "tp_id") {
                    $fields[$key2] = $value2;
                }
                if (isset($tp_name)) {
                    $fields["tp_name"] = $tp_name;
                }
            }
            if (isset($tp_name) && testTPExistence($tp_name)) {
                $params = [
                    'values' => $val,
                    'timeperiod_id' => $key
                ];
                $tpId = duplicateTimePeriod($params);
                $centreon->CentreonLogAction->insertLog(
                    object_type: ActionLog::OBJECT_TYPE_TIMEPERIOD,
                    object_id: $tpId,
                    object_name: $tp_name,
                    action_type: ActionLog::ACTION_TYPE_ADD,
                    fields: $fields
                );
            }
        }
    }
}

/**
 * Form validator.
 */
function checkHours($hourString)
{
    if ($hourString == "") {
        return true;
    } elseif (strstr($hourString, ",")) {
        $tab1 = preg_split("/\,/", $hourString);
        for ($i = 0; isset($tab1[$i]); $i++) {
            if (preg_match("/([0-9]*):([0-9]*)-([0-9]*):([0-9]*)/", $tab1[$i], $str)) {
                if ($str[1] > 24 || $str[3] > 24) {
                    return false;
                }
                if ($str[2] > 59 || $str[4] > 59) {
                    return false;
                }
                if (($str[3] * 60 * 60 + $str[4] * 60) > 86400 || ($str[1] * 60 * 60 + $str[2] * 60) > 86400) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    } elseif (preg_match("/([0-9]*):([0-9]*)-([0-9]*):([0-9]*)/", $hourString, $str)) {
        if ($str[1] > 24 || $str[3] > 24) {
            return false;
        }
        if ($str[2] > 59 || $str[4] > 59) {
            return false;
        }
        if (($str[3] * 60 * 60 + $str[4] * 60) > 86400 || ($str[1] * 60 * 60 + $str[2] * 60) > 86400) {
            return false;
        }
        return true;
    } else {
        return false;
    }
}

/**
 * Get time period id by name
 *
 * @param string $name
 * @return int
 */
function getTimeperiodIdByName($name)
{
    global $pearDB;

    $id = 0;
    $res = $pearDB->query("SELECT tp_id FROM timeperiod WHERE tp_name = '" . $pearDB->escape($name) . "'");
    if ($res->rowCount()) {
        $row = $res->fetch();
        $id = $row['tp_id'];
    }
    return $id;
}

/**
 * Get chain of time periods via template relation
 *
 * @global \Pimple\Container $dependencyInjector
 * @param array $tpIds List of selected time period as IDs
 * @return array
 */
function getTimeperiodsFromTemplate(array $tpIds)
{
    global $dependencyInjector;

    $db = $dependencyInjector['centreon.db-manager'];

    $result = [];

    foreach ($tpIds as $tpId) {
        $db->getRepository(Centreon\Domain\Repository\TimePeriodRepository::class)
            ->getIncludeChainByParent($tpId, $result);
    }

    return $result;
}

/**
 * Validator prevent loops via template
 *
 * @global \HTML_QuickFormCustom $form Access to the form object
 * @param array $value List of selected time period as IDs
 * @return bool
 */
function testTemplateLoop($value)
{
    // skip check if template field is empty
    if (!$value) {
        return true;
    }

    global $form;

    $data = $form->getSubmitValues();

    // skip check if timeperiod is new
    if (!$data['tp_id']) {
        return true;
    } elseif (in_array($data['tp_id'], $value)) {
        // try to skip heavy check of templates

        return false;
    } elseif (in_array($data['tp_id'], getTimeperiodsFromTemplate($value))) {
        // get list of all timeperiods related via templates

        return false;
    }

    return true;
}

/**
 * All in one function to duplicate time periods
 *
 * @param array $params
 * @return int
 */
function duplicateTimePeriod(array $params): int
{
    global $pearDB;

    $isAlreadyInTransaction = $pearDB->inTransaction();
    if (!$isAlreadyInTransaction) {
        $pearDB->beginTransaction();
    }
    try {
        $params['tp_id'] = createTimePeriod($params);
        createTimePeriodsExceptions($params);
        createTimePeriodsIncludeRelations($params);
        createTimePeriodsExcludeRelations($params);
        if (!$isAlreadyInTransaction) {
            $pearDB->commit();
        }
    } catch (\Exception $e) {
        if (!$isAlreadyInTransaction) {
            $pearDB->rollBack();
        }
    }
    return $params['tp_id'];
}

/**
 * Creates time period and returns id.
 *
 * @param array $params
 * @return int
 */
function createTimePeriod(array $params): int
{
    global $pearDB;

    $queryBindValues = [];
    foreach ($params['values'] as $index => $value) {
        $queryBindValues[':value_' . $index] = $value;
    }
    $bindValues = implode(', ', array_keys($queryBindValues));
    $statement = $pearDB->prepare("INSERT INTO timeperiod VALUES ($bindValues)");
    foreach ($queryBindValues as $bindKey => $bindValue) {
        if (array_key_first($queryBindValues) === $bindKey) {
            $statement->bindValue($bindKey, (int) $bindValue, \PDO::PARAM_INT);
        } else {
            $statement->bindValue($bindKey, $bindValue, \PDO::PARAM_STR);
        }
    }
    $statement->execute();
    return (int) $pearDB->lastInsertId();
}

/**
 * Creates time periods exclude relations
 *
 * @param array $params
 */
function createTimePeriodsExcludeRelations(array $params): void
{
    global $pearDB;

    $query = "INSERT INTO timeperiod_exclude_relations (timeperiod_id, timeperiod_exclude_id) " .
             "SELECT :tp_id, timeperiod_exclude_id FROM timeperiod_exclude_relations " .
             "WHERE timeperiod_id = :timeperiod_id";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':tp_id', $params['tp_id'], \PDO::PARAM_INT);
    $statement->bindValue(':timeperiod_id', (int) $params['timeperiod_id'], \PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Creates time periods include relations
 *
 * @param array $params
 */
function createTimePeriodsIncludeRelations(array $params): void
{
    global $pearDB;

    $query = "INSERT INTO timeperiod_include_relations (timeperiod_id, timeperiod_include_id) " .
             "SELECT :tp_id, timeperiod_include_id FROM timeperiod_include_relations " .
             "WHERE timeperiod_id = :timeperiod_id";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':tp_id', $params['tp_id'], \PDO::PARAM_INT);
    $statement->bindValue(':timeperiod_id', (int) $params['timeperiod_id'], \PDO::PARAM_INT);
    $statement->execute();
}

/**
 * Creates time periods exceptions
 *
 * @param array $params
 */
function createTimePeriodsExceptions(array $params): void
{
    global $pearDB;

    $query = "INSERT INTO timeperiod_exceptions (timeperiod_id, days, timerange) " .
             "SELECT :tp_id, days, timerange FROM timeperiod_exceptions " .
             "WHERE timeperiod_id = :timeperiod_id";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':tp_id', $params['tp_id'], \PDO::PARAM_INT);
    $statement->bindValue(':timeperiod_id', (int) $params['timeperiod_id'], \PDO::PARAM_INT);
    $statement->execute();
}

// ----------------- API CALLS --------------------

/**
 * Create a new timeperiod form formData.
 *
 * @param array<mixed> $ret
 *
 * @return int|null
 */
function insertTimePeriodInAPI(array $ret = []): int|null
{
    global $form, $basePath;

    $formData = $ret === [] ? $form->getSubmitValues() : $ret;

    try {
        return insertTimeperiodByApi($formData, $basePath);
    } catch (Throwable $th) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while inserting timeperiod by api : {$th->getMessage()}",
            customContext: ['form_data' => $formData, 'base_path' => $basePath],
            exception: $th
        );

        echo "<div class='msg' align='center'>" . _($th->getMessage()) . '</div>';

        return null;
    }
}

/**
 * Make the API request to create a new timeeperiod and return the new ID.
 *
 * @param array $formData
 * @param string $basePath
 *
 * @throws Throwable
 *
 * @return int
 */
function insertTimeperiodByApi(array $formData, string $basePath): int
{
    $kernel = Kernel::createForWeb();
    /** @var Router $router */
    $router = $kernel->getContainer()->get(Router::class);
    $client = new CurlHttpClient();

    $payload = getPayloadForTimePeriod($formData);
    $url = $router->generate(
        'AddTimePeriod',
        $basePath ? ['base_uri' => $basePath] : [],
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    $headers = [
        'Content-Type' => 'application/json',
        'Cookie' => 'PHPSESSID=' . $_COOKIE['PHPSESSID'],
    ];
    $response = $client->request(
        'POST',
        $url,
        [
            'headers' => $headers,
            'body' => json_encode(value: $payload, flags: JSON_THROW_ON_ERROR),
        ],
    );

    if ($response->getStatusCode() !== 201) {
        $content = json_decode(json: $response->getContent(false), flags: JSON_THROW_ON_ERROR);

        throw new Exception($content->message ?? 'Unexpected return status');
    }

    $data = $response->toArray();

    /** @var array{id:int} $data */
    return $data['id'];
}

/**
 * Update a timeperiod.
 *
 * @param mixed $tp_id
 *
 * @return bool
 */
function updateTimeperiodInAPI($tp_id = null): bool
{
    if (! $tp_id) {
        return true;
    }

    global $form, $basePath;

    $formData = $form->getSubmitValues();

    try {
        updateTimeperiodByApi($formData, $basePath);

        return true;
    } catch (Throwable $th) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while updating timeperiod by api : {$th->getMessage()}",
            customContext: ['form_data' => $formData, 'base_path' => $basePath],
            exception: $th
        );

        echo "<div class='msg' align='center'>" . _($th->getMessage()) . '</div>';

        return false;
    }
}

/**
 * Make the API request to update a timeeperiod .
 * @param array $formData
 * @param string $basePath
 *
 * @throws Throwable
 *
 * @return void
 */
function updateTimeperiodByApi(array $formData, string $basePath): void
{
    $kernel = Kernel::createForWeb();
    /** @var Router $router */
    $router = $kernel->getContainer()->get(Router::class);
    $client = new CurlHttpClient();

    $payload = getPayloadForTimePeriod($formData);
    $url = $router->generate(
        'UpdateTimePeriod',
        $basePath ? ['base_uri' => $basePath, 'id' => $formData['tp_id']] : [],
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    $headers = [
        'Content-Type' => 'application/json',
        'Cookie' => 'PHPSESSID=' . $_COOKIE['PHPSESSID'],
    ];
    $response = $client->request(
        'PUT',
        $url,
        [
            'headers' => $headers,
            'body' => json_encode(value: $payload, flags: JSON_THROW_ON_ERROR),
        ],
    );

    if ($response->getStatusCode() !== 204) {
        $content = json_decode(json: $response->getContent(false), flags: JSON_THROW_ON_ERROR);

        throw new Exception($content->message ?? 'Unexpected return status');
    }
}

/**
 * @param int[] $timeperiods
 *
 * @return bool
 */
function deleteTimePeriodInAPI(array $timeperiods = []): bool
{
    global $basePath;

   try {
        deleteTimeperiodByApi($basePath, $timeperiods);

        return true;
    } catch (Throwable $th) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while deleting timeperiod by api : {$th->getMessage()}",
            customContext: ['timeperiods' => $timeperiods, 'base_path' => $basePath],
            exception: $th
        );

        echo "<div class='msg' align='center'>" . _($th->getMessage()) . '</div>';

        return false;
    }
}

/**
 * @param string $basePath
 * @param int[] $timePeriodIds
 *
 * @throws Throwable
 */
function deleteTimePeriodByAPI(string $basePath, array $timePeriodIds): void
{
    $kernel = Kernel::createForWeb();
    /** @var Router $router */
    $router = $kernel->getContainer()->get(Router::class);
    $client = new CurlHttpClient();

    $headers = [
        'Content-Type' => 'application/json',
        'Cookie' => 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . ';XDEBUG_SESSION=XDEBUG_KEY',
    ];

    foreach ($timePeriodIds as $id) {
        $url = $router->generate(
            'DeleteTimePeriod',
            $basePath ? ['base_uri' => $basePath, 'id' => $id] : [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $response = $client->request('DELETE', $url, ['headers' => $headers]);

        if ($response->getStatusCode() !== 204) {
            $content = json_decode($response->getContent(false), true);
            $message = $content['message'] ?? 'Unknown error';

            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: "Error while deleting timeperiod by API : {$message}",
                customContext: ['timeperiod_id' => $id]
            );
        }
    }
}

/**
 * @param array<mixed> $formData
 *
 * @return array<string,mixed>
 */
function getPayloadForTimePeriod(array $formData): array
{
    $days = [];
    $exceptions = [];
    $weekDays = [
        'tp_monday' => 1,
        'tp_tuesday' => 2,
        'tp_wednesday' => 3,
        'tp_thursday' => 4,
        'tp_friday' => 5,
        'tp_saturday' => 6,
        'tp_sunday' => 7,
    ];
    foreach ($formData as $name => $value) {
        if (str_starts_with($name, 'exceptionInput_')) {
            $exceptions[] = [
                'day_range' => $value,
                'time_range' => $formData[str_replace('Input', 'Timerange', $name)],
            ];
        }
        if (in_array($name, array_keys($weekDays), true) && $value !== '') {
            $days[] = ['day' => $weekDays[$name], 'time_range' => $value];
        }
    }

    return [
        'name' => $formData['tp_name'],
        'alias' => $formData['tp_alias'],
        'days' => $days,
        'templates' => array_map(static fn(string $id): int => (int) $id, $formData['tp_include'] ?? []),
        'exceptions' => $exceptions,
    ];
}
