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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/webService.class.php';

/**
 * Class
 *
 * @class CentreonRealtimeBase
 */
class CentreonRealtimeBase extends CentreonWebService
{
    /** @var CentreonDB */
    protected $realTimeDb;

    /**
     * CentreonConfigurationObjects constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->realTimeDb = new CentreonDB('centstorage');
    }

    /**
     * @throws RestBadRequestException
     * @return array
     */
    public function getDefaultValues()
    {
        // Get Object targeted
        if (isset($this->arguments['id']) && ! empty($this->arguments['id'])) {
            $id = $this->arguments['id'];
        } else {
            throw new RestBadRequestException('Bad parameters id');
        }

        // Get Object targeted
        if (isset($this->arguments['field'])) {
            $field = $this->arguments['field'];
        } else {
            throw new RestBadRequestException('Bad parameters field');
        }

        // Get Object targeted
        if (isset($this->arguments['target'])) {
            $target = ucfirst($this->arguments['target']);
        } else {
            throw new RestBadRequestException('Bad parameters target');
        }

        $defaultValuesParameters = [];
        $targetedFile = _CENTREON_PATH_ . "/www/class/centreon{$target}.class.php";
        if (file_exists($targetedFile)) {
            require_once $targetedFile;
            $calledClass = 'Centreon' . $target;
            $defaultValuesParameters = $calledClass::getDefaultValuesParameters($field);
        }

        if (count($defaultValuesParameters) == 0) {
            throw new RestBadRequestException('Bad parameters count');
        }

        if (isset($defaultValuesParameters['type']) && $defaultValuesParameters['type'] === 'simple') {
            if (isset($defaultValuesParameters['reverse']) && $defaultValuesParameters['reverse']) {
                $selectedValues = $this->retrieveSimpleValues(
                    ['table' => $defaultValuesParameters['externalObject']['table'], 'id' => $defaultValuesParameters['currentObject']['id']],
                    $id,
                    $defaultValuesParameters['externalObject']['id']
                );
            } else {
                $selectedValues = $this->retrieveSimpleValues($defaultValuesParameters['currentObject'], $id, $field);
            }
        } elseif (isset($defaultValuesParameters['type']) && $defaultValuesParameters['type'] === 'relation') {
            $selectedValues = $this->retrieveRelatedValues($defaultValuesParameters['relationObject'], $id);
        } else {
            throw new RestBadRequestException('Bad parameters');
        }

        // Manage final data
        $finalDatas = [];
        if (count($selectedValues) > 0) {
            $finalDatas = $this->retrieveExternalObjectDatas(
                $defaultValuesParameters['externalObject'],
                $selectedValues
            );
        }

        return $finalDatas;
    }

    /**
     * @param $externalObject
     * @param $values
     *
     * @throws PDOException
     * @return array
     */
    protected function retrieveExternalObjectDatas($externalObject, $values)
    {
        $tmpValues = [];

        if (isset($externalObject['object'])) {
            $classFile = $externalObject['object'] . '.class.php';
            include_once _CENTREON_PATH_ . "/www/class/{$classFile}";
            $calledClass = ucfirst($externalObject['object']);
            $externalObjectInstance = new $calledClass($this->pearDB);

            $options = [];
            if (isset($externalObject['objectOptions'])) {
                $options = $externalObject['objectOptions'];
            }
            try {
                $tmpValues = $externalObjectInstance->getObjectForSelect2($values, $options);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        } else {
            $explodedValues = '';
            $queryValues = [];

            if (! empty($values)) {
                foreach ($values as $key => $value) {
                    $explodedValues .= ':object' . $key . ',';
                    $queryValues['object'][$key] = $value;
                }
                $explodedValues = rtrim($explodedValues, ',');
            }

            $query = "SELECT {$externalObject['id']}, {$externalObject['name']} "
                . "FROM {$externalObject['table']} "
                . "WHERE {$externalObject['comparator']} "
                . "IN ({$explodedValues})";
            $stmt = $this->pearDB->prepare($query);

            if (isset($queryValues['object'])) {
                foreach ($queryValues['object'] as $key => $value) {
                    $stmt->bindValue(':object' . $key, $value);
                }
            }
            $stmt->execute();

            while ($row = $stmt->fetch()) {
                $tmpValues[] = ['id' => $row[$externalObject['id']], 'text' => $row[$externalObject['name']]];
            }
        }

        return $tmpValues;
    }

    /**
     * @param $currentObject
     * @param $id
     * @param $field
     * @return array
     */
    protected function retrieveSimpleValues($currentObject, $id, $field)
    {
        $tmpValues = [];

        $fields = [];
        $fields[] = $field;
        if (isset($currentObject['additionalField'])) {
            $fields[] = $currentObject['additionalField'];
        }

        // Getting Current Values
        $queryValuesRetrieval = 'SELECT ' . implode(', ', $fields) . ' '
            . 'FROM ' . $currentObject['table'] . ' '
            . 'WHERE ' . $currentObject['id'] . ' = :objectId';

        $stmt = $this->pearDB->prepare($queryValuesRetrieval);
        $stmt->bindParam(':objectId', $id, PDO::PARAM_INT);

        while ($row = $stmt->fetch()) {
            $tmpValue = $row[$field];
            if (isset($currentObject['additionalField'])) {
                $tmpValue .= '-' . $row[$currentObject['additionalField']];
            }
            $tmpValues[] = $tmpValue;
        }

        return $tmpValues;
    }

    /**
     * @param $relationObject
     * @param $id
     * @return array
     */
    protected function retrieveRelatedValues($relationObject, $id)
    {
        $tmpValues = [];

        $fields = [];
        $fields[] = $relationObject['field'];
        if (isset($relationObject['additionalField'])) {
            $fields[] = $relationObject['additionalField'];
        }

        $queryValuesRetrieval = 'SELECT ' . implode(', ', $fields) . ' '
            . 'FROM ' . $relationObject['table'] . ' '
            . 'WHERE ' . $relationObject['comparator'] . ' = :comparatorId';
        $stmt = $this->pearDB->prepare($queryValuesRetrieval);
        $stmt->bindParam(':comparatorId', $id, PDO::PARAM_INT);

        while ($row = $stmt->fetch()) {
            if (! empty($row[$relationObject['field']])) {
                $tmpValue = $row[$relationObject['field']];
                if (isset($relationObject['additionalField'])) {
                    $tmpValue .= '-' . $row[$relationObject['additionalField']];
                }
                $tmpValues[] = $tmpValue;
            }
        }

        return $tmpValues;
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        return (bool) (
            parent::authorize($action, $user, $isInternal)
            || ($user && $user->hasAccessRestApiRealtime())
        );
    }
}
