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
 * Class
 *
 * Manage new feature
 *
 * Format:
 * $availableFeatures = array( array(
 * 'name' => 'Header',
 * 'version' => 2,
 * 'description' => 'New header design user experience',
 * 'visible' => true))
 *
 * @class CentreonFeature
 */
class CentreonFeature
{
    /** @var CentreonDB */
    protected $db;

    /** @var array */
    protected static $availableFeatures = [];

    /**
     * CentreonFeature constructor
     *
     * @param CentreonDB $db - The centreon database
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Return the list of new feature to test
     *
     * @param int $userId - The user id
     *
     * @throws PDOException
     * @return array - The list of new feature to ask at the user
     */
    public function toAsk($userId)
    {
        if (! is_numeric($userId)) {
            throw new Exception('The user id is not numeric.');
        }
        $result = [];
        if (count(self::$availableFeatures) != 0) {
            $query = 'SELECT feature, feature_version FROM contact_feature WHERE contact_id = ' . $userId;
            $res = $this->db->query($query);
            $toAsk = [];
            foreach (self::$availableFeatures as $feature) {
                if ($feature['visible']) {
                    $version = $feature['name'] . '__' . $feature['version'];
                    $toAsk[$version] = $feature;
                }
            }
            while ($row = $res->fetchRow()) {
                $version = $row['feature'] . '__' . $row['feature_version'];
                unset($toAsk[$version]);
            }
            foreach ($toAsk as $feature) {
                $result[] = $feature;
            }
        }

        return $result;
    }

    /**
     * Return the list of features to test
     *
     * @return array
     */
    public function getFeatures()
    {
        $result = [];
        foreach (self::$availableFeatures as $feature) {
            if ($feature['visible']) {
                $result[] = $feature;
            }
        }

        return $result;
    }

    /**
     * Return the list of feature for an user and the activated value
     *
     * @param int $userId - The user id
     *
     * @throws PDOException
     * @return array
     */
    public function userFeaturesValue($userId)
    {
        if (! is_numeric($userId)) {
            throw new Exception('The user id is not numeric.');
        }
        $query = 'SELECT feature, feature_version, feature_enabled FROM contact_feature WHERE contact_id = ' . $userId;
        $res = $this->db->query($query);
        $result = [];
        while ($row = $res->fetchRow()) {
            $result[] = ['name' => $row['feature'], 'version' => $row['feature_version'], 'enabled' => $row['feature_enabled']];
        }

        return $result;
    }

    /**
     * Save the user choices for feature flipping
     *
     * @param int $userId - The user id
     * @param array $features - The list of features
     *
     * @throws PDOException
     */
    public function saveUserFeaturesValue($userId, $features): void
    {
        if (! is_numeric($userId)) {
            throw new Exception('The user id is not numeric.');
        }
        foreach ($features as $name => $versions) {
            foreach ($versions as $version => $value) {
                $query = 'DELETE FROM contact_feature WHERE contact_id = ' . $userId . ' AND feature = "'
                    . $this->db->escape($name) . '" AND feature_version = "' . $this->db->escape($version) . '"';
                $this->db->query($query);
                $query = 'INSERT INTO contact_feature VALUES (' . $userId . ', "' . $this->db->escape($name) . '", "'
                    . $this->db->escape($version) . '", ' . (int) $value . ')';
                $this->db->query($query);
            }
        }
    }

    /**
     * Get if a feature is active for the application or an user
     *
     * @param string $name - The feature name
     * @param string $version - The feature version
     * @param null $userId - The user id if check for an user
     *
     * @throws Exception
     * @return bool
     */
    public function featureActive($name, $version, $userId = null)
    {
        foreach (self::$availableFeatures as $feature) {
            if ($feature['name'] === $name && $feature['version'] === $version && ! $feature['visible']) {
                return false;
            }
        }
        if (is_null($userId)) {
            return true;
        }
        if (! is_numeric($userId)) {
            throw new Exception('The user id is not numeric.');
        }
        $query = 'SELECT feature_enabled FROM contact_feature
            WHERE contact_id = ' . $userId . ' AND feature = "' . $this->db->escape($name) . '"
                AND feature_version = "' . $this->db->escape($version) . '"';
        try {
            $res = $this->db->query($query);
        } catch (Exception $e) {
            return false;
        }
        if ($res->rowCount() === 0) {
            return false;
        }
        $row = $res->fetch();

        return $row['feature_enabled'] == 1;
    }
}
