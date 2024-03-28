<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonLegacy\Core\Widget;

class Upgrader extends Installer
{
    /**
     * @throws \Exception
     *
     * @return bool
     */
    public function upgrade()
    {
        if (! $this->informationObj->isInstalled($this->widgetName)) {
            throw new \Exception('Widget "' . $this->widgetName . '" is not installed.');
        }

        try {
            $id = $this->upgradeConfiguration();
            $this->upgradePreferences($id);
            $upgraded = true;
        } catch (\Exception $e) {
            $upgraded = false;
        }

        return $upgraded;
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    protected function upgradeConfiguration()
    {
        $query = 'UPDATE widget_models SET '
            . 'title = :title, '
            . 'description = :description, '
            . 'url = :url, '
            . 'version = :version, '
            . 'author = :author, '
            . 'email = :email, '
            . 'website = :website, '
            . 'keywords = :keywords, '
            . 'thumbnail = :thumbnail, '
            . 'autoRefresh = :autoRefresh '
            . 'WHERE directory = :directory ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':title', $this->widgetConfiguration['title'], \PDO::PARAM_STR);
        $sth->bindParam(':description', $this->widgetConfiguration['description'], \PDO::PARAM_STR);
        $sth->bindParam(':url', $this->widgetConfiguration['url'], \PDO::PARAM_STR);
        $sth->bindParam(':version', $this->widgetConfiguration['version'], \PDO::PARAM_STR);
        $sth->bindParam(':author', $this->widgetConfiguration['author'], \PDO::PARAM_STR);
        $sth->bindParam(':email', $this->widgetConfiguration['email'], \PDO::PARAM_STR);
        $sth->bindParam(':website', $this->widgetConfiguration['website'], \PDO::PARAM_STR);
        $sth->bindParam(':keywords', $this->widgetConfiguration['keywords'], \PDO::PARAM_STR);
        $sth->bindParam(':thumbnail', $this->widgetConfiguration['thumbnail'], \PDO::PARAM_STR);
        $sth->bindParam(':autoRefresh', $this->widgetConfiguration['autoRefresh'], \PDO::PARAM_INT);
        $sth->bindParam(':directory', $this->widgetConfiguration['directory'], \PDO::PARAM_STR);

        if (! $sth->execute()) {
            throw new \Exception('Cannot upgrade widget "' . $this->widgetName . '".');
        }

        return $this->informationObj->getIdByName($this->widgetName);
    }

    /**
     * @param int $id
     * @param array $parameters
     * @param array $preference
     */
    protected function updateParameters($id, $parameters, $preference)
    {
        $query = 'UPDATE widget_parameters SET '
            . 'field_type_id = :field_type_id, '
            . 'parameter_name = :parameter_name, '
            . 'default_value = :default_value, '
            . 'parameter_order = :parameter_order, '
            . 'require_permission = :require_permission, '
            . 'header_title = :header_title '
            . 'WHERE widget_model_id = :widget_model_id '
            . 'AND parameter_code_name = :parameter_code_name ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':field_type_id', $parameters['type']['id'], \PDO::PARAM_INT);
        $sth->bindParam(':parameter_name', $parameters['label'], \PDO::PARAM_STR);
        $sth->bindParam(':default_value', $parameters['defaultValue'], \PDO::PARAM_STR);
        $sth->bindParam(':parameter_order', $parameters['order'], \PDO::PARAM_STR);
        $sth->bindParam(':require_permission', $parameters['requirePermission'], \PDO::PARAM_STR);
        $sth->bindParam(':header_title', $parameters['header'], \PDO::PARAM_STR);
        $sth->bindParam(':widget_model_id', $id, \PDO::PARAM_INT);
        $sth->bindParam(':parameter_code_name', $parameters['name'], \PDO::PARAM_STR);

        $sth->execute();

        $lastId = $this->informationObj->getParameterIdByName($parameters['name'], $id);
        $this->deleteParameterOptions($lastId);

        switch ($parameters['type']['name']) {
            case 'list':
            case 'sort':
                $this->installMultipleOption($lastId, $preference);
                break;
            case 'range':
                $this->installRangeOption($lastId, $parameters);
                break;
        }
    }

    /**
     * @param int $id
     */
    protected function deleteParameter($id)
    {
        $query = 'DELETE FROM widget_parameters '
            . 'WHERE parameter_id = :id ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':id', $id, \PDO::PARAM_INT);

        $sth->execute();
    }

    /**
     * @param int $id
     */
    protected function deleteParameterOptions($id)
    {
        $query = 'DELETE FROM widget_parameters_multiple_options '
            . 'WHERE parameter_id = :id ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':id', $id, \PDO::PARAM_INT);

        $sth->execute();

        $query = 'DELETE FROM widget_parameters_range '
            . 'WHERE parameter_id = :id ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':id', $id, \PDO::PARAM_INT);

        $sth->execute();
    }

    /**
     * @param int $widgetId
     *
     * @throws \Exception
     */
    private function upgradePreferences($widgetId): void
    {
        if (! isset($this->widgetConfiguration['preferences'])) {
            return;
        }

        $types = $this->informationObj->getTypes();

        $existingParams = $this->informationObj->getParameters($widgetId);

        $insertedParameters = [];
        foreach ($this->widgetConfiguration['preferences'] as $preferences) {
            if (! is_array($preferences)) {
                continue;
            }
            $order = 1;
            if (isset($preferences['@attributes'])) {
                $preferences = [$preferences['@attributes']];
            }

            foreach ($preferences as $preference) {
                $attr = $preference['@attributes'];
                if (! isset($types[$attr['type']])) {
                    throw new \Exception('Unknown type : ' . $attr['type'] . ' found in configuration file');
                }
                $attr['requirePermission'] ??= 0;
                $attr['defaultValue'] ??= '';
                $attr['header'] = (isset($attr['header']) && $attr['header'] != '') ? $attr['header'] : null;
                $attr['order'] = $order;
                $attr['type'] = $types[$attr['type']];
                if (! isset($existingParams[$attr['name']])) {
                    $this->installParameters($widgetId, $attr, $preference);
                } else {
                    $this->updateParameters($widgetId, $attr, $preference);
                }
                $insertedParameters[] = $attr['name'];
                $order++;
            }
        }

        foreach ($existingParams as $name => $attributes) {
            if (! in_array($name, $insertedParameters)) {
                $this->deleteParameter($attributes['parameter_id']);
            }
        }
    }
}
