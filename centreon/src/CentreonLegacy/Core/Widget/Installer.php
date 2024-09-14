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

class Installer extends Widget
{
    /**
     * @throws \Exception
     *
     * @return int
     */
    public function install()
    {
        if ($this->informationObj->isInstalled($this->widgetName)) {
            throw new \Exception('Widget is already installed.');
        }

        $id = $this->installConfiguration();
        $this->installPreferences($id);

        return $id;
    }

    /**
     * @return int
     */
    protected function installConfiguration()
    {
        $query = 'INSERT INTO widget_models '
            . '(title, description, url, version, is_internal, directory, author, '
            . 'email, website, keywords, thumbnail, autoRefresh) '
            . 'VALUES (:title, :description, :url, :version, :is_internal, :directory, :author, '
            . ':email, :website, :keywords, :thumbnail, :autoRefresh) ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindValue(':title', $this->widgetConfiguration['title'], \PDO::PARAM_STR);
        $sth->bindValue(':description', $this->widgetConfiguration['description'], \PDO::PARAM_STR);
        $sth->bindValue(':url', $this->widgetConfiguration['url'], \PDO::PARAM_STR);
        $sth->bindValue(':version', $this->widgetConfiguration['version'], \PDO::PARAM_STR);
        $sth->bindValue(':is_internal', $this->widgetConfiguration['version'] === null, \PDO::PARAM_BOOL);
        $sth->bindValue(':directory', $this->widgetConfiguration['directory'], \PDO::PARAM_STR);
        $sth->bindValue(':author', $this->widgetConfiguration['author'], \PDO::PARAM_STR);
        $sth->bindValue(':email', $this->widgetConfiguration['email'], \PDO::PARAM_STR);
        $sth->bindValue(':website', $this->widgetConfiguration['website'], \PDO::PARAM_STR);
        $sth->bindValue(':keywords', $this->widgetConfiguration['keywords'], \PDO::PARAM_STR);
        $sth->bindValue(':thumbnail', $this->widgetConfiguration['thumbnail'], \PDO::PARAM_STR);
        $sth->bindValue(':autoRefresh', $this->widgetConfiguration['autoRefresh'], \PDO::PARAM_INT);

        $sth->execute();

        return $this->informationObj->getIdByName($this->widgetName);
    }

    /**
     * @param int $id
     *
     * @throws \Exception
     */
    protected function installPreferences($id)
    {
        if (! isset($this->widgetConfiguration['preferences'])) {
            return;
        }

        $types = $this->informationObj->getTypes();

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

                $this->installParameters($id, $attr, $preference);
                $order++;
            }
        }
    }

    /**
     * @param int $id
     * @param array $parameters
     * @param array $preference
     */
    protected function installParameters($id, $parameters, $preference)
    {
        $query = 'INSERT INTO widget_parameters '
            . '(widget_model_id, field_type_id, parameter_name, parameter_code_name, '
            . 'default_value, parameter_order, require_permission, header_title) '
            . 'VALUES '
            . '(:widget_model_id, :field_type_id, :parameter_name, :parameter_code_name, '
            . ':default_value, :parameter_order, :require_permission, :header_title) ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':widget_model_id', $id, \PDO::PARAM_INT);
        $sth->bindParam(':field_type_id', $parameters['type']['id'], \PDO::PARAM_INT);
        $sth->bindParam(':parameter_name', $parameters['label'], \PDO::PARAM_STR);
        $sth->bindParam(':parameter_code_name', $parameters['name'], \PDO::PARAM_STR);
        $sth->bindParam(':default_value', $parameters['defaultValue'], \PDO::PARAM_STR);
        $sth->bindParam(':parameter_order', $parameters['order'], \PDO::PARAM_STR);
        $sth->bindParam(':require_permission', $parameters['requirePermission'], \PDO::PARAM_STR);
        $sth->bindParam(':header_title', $parameters['header'], \PDO::PARAM_STR);

        $sth->execute();

        $lastId = $this->informationObj->getParameterIdByName($parameters['name'], $id);

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
     * @param int $paramId
     * @param array $preference
     */
    protected function installMultipleOption($paramId, $preference)
    {
        if (! isset($preference['option'])) {
            return;
        }

        $query = 'INSERT INTO widget_parameters_multiple_options '
            . '(parameter_id, option_name, option_value) VALUES '
            . '(:parameter_id, :option_name, :option_value) ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        foreach ($preference['option'] as $option) {
            $opt = isset($option['@attributes']) ? $option['@attributes'] : $option;

            $sth->bindParam(':parameter_id', $paramId, \PDO::PARAM_INT);
            $sth->bindParam(':option_name', $opt['label'], \PDO::PARAM_STR);
            $sth->bindParam(':option_value', $opt['value'], \PDO::PARAM_STR);

            $sth->execute();
        }
    }

    /**
     * @param int $paramId
     * @param array $parameters
     */
    protected function installRangeOption($paramId, $parameters)
    {
        $query = 'INSERT INTO widget_parameters_range (parameter_id, min_range, max_range, step) '
            . 'VALUES (:parameter_id, :min_range, :max_range, :step) ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':parameter_id', $paramId, \PDO::PARAM_INT);
        $sth->bindParam(':min_range', $parameters['min'], \PDO::PARAM_INT);
        $sth->bindParam(':max_range', $parameters['max'], \PDO::PARAM_INT);
        $sth->bindParam(':step', $parameters['step'], \PDO::PARAM_INT);

        $sth->execute();
    }
}
