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

namespace CentreonClapi;

require_once 'centreonObject.class.php';

/**
 * Class
 *
 * @class CentreonSeverityAbstract
 * @package CentreonClapi
 */
abstract class CentreonSeverityAbstract extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_ALIAS = 1;

    /**
     * Set severity
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function setseverity($parameters): void
    {
        $params = explode($this->delim, $parameters);
        $uniqueLabel = $params[self::ORDER_UNIQUENAME];
        if (count($params) < 3) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $objectId = $this->getObjectId($uniqueLabel);

        if ($objectId != 0) {
            if (! is_numeric($params[1])) {
                throw new CentreonClapiException('Incorrect severity level parameters');
            }
            $level = (int) $params[1];
            $iconId = CentreonUtils::getImageId($params[2], $this->db);
            if (is_null($iconId)) {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[2]);
            }
            $updateParams = ['level' => $level, 'icon_id' => $iconId];

            $this->object->update($objectId, $updateParams);
            $this->addAuditLog(
                'c',
                $objectId,
                $uniqueLabel,
                $updateParams
            );
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[self::ORDER_UNIQUENAME]);
        }
    }

    /**
     * Unset severity
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function unsetseverity($parameters): void
    {
        $params = explode($this->delim, $parameters);
        $uniqueLabel = $params[self::ORDER_UNIQUENAME];
        if (count($params) < 1) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $objectId = $this->getObjectId($uniqueLabel);
        if ($objectId != 0) {
            $updateParams = ['level' => null, 'icon_id' => null];

            $this->object->update($objectId, $updateParams);
            $this->addAuditLog(
                'c',
                $objectId,
                $uniqueLabel,
                $updateParams
            );
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[self::ORDER_UNIQUENAME]);
        }
    }
}
