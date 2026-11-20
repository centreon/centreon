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

namespace CentreonRemote\Application\Webservice;

class CentreonConfigurationTopology extends CentreonWebServiceAbstract
{
    /**
     * Name of web service object.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_configuration_topology';
    }

    /**
     * Get data for topology_id
     *
     * @throws \RestBadRequestException
     *
     * @return array<string,string|bool>
     */
    public function postGetTopologyData()
    {
        if (! isset($_POST['topology_id']) || ! $_POST['topology_id']) {
            throw new \RestBadRequestException('You need to send \'topology_id\' in the request.');
        }

        $topologyID = (int) $_POST['topology_id'];
        $statement = $this->pearDB->prepare(
            'SELECT `topology_url`, `is_react` FROM `topology` WHERE `topology_id` = :id'
        );
        $statement->execute([':id' => $topologyID]);
        $result = $statement->fetch();

        if (! $result) {
            throw new \RestBadRequestException('No topology found.');
        }

        return [
            'url' => $result['topology_url'],
            'is_react' => (bool) $result['is_react'],
        ];
    }

    /**
     * Authorize to access to the action.
     *
     * @param string $action The action name
     * @param \CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     *
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        if (parent::authorize($action, $user, $isInternal)) {
            return true;
        }

        return $user->hasAccessRestApiConfiguration();
    }
}
