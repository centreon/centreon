<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace CentreonModule\Application\Webservice;

use CentreonRemote\Application\Webservice\CentreonWebServiceAbstract;

/**
 * @OA\Tag(name="centreon_modules_webservice", description="Resource for external public access")
 */
class CentreonModulesWebservice extends CentreonWebServiceAbstract
{
    /**
     * @OA\Post(
     *   path="/external.php?object=centreon_modules_webservice&action=getBamModuleInfo",
     *   description="Get list of modules and widgets",
     *   tags={"centreon_modules_webservice"},
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *       description="the name of the API object class",
     *       required=true,
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_modules_webservice"}
     *       )
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *       description="the name of the action in the API class",
     *       required=true,
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"getBamModuleInfo"}
     *       )
     *   ),
     *
     *   @OA\Response(
     *      response="200",
     *      description="JSON with BAM module info",
     *
     *      @OA\JsonContent(
     *
     *          @OA\Property(property="enabled", type="boolean"),
     *          @OA\Property(property="status", type="boolean")
     *      )
     *   )
     * )
     *
     * Get info for BAM module
     *
     * @return array<string,bool>
     */
    public function postGetBamModuleInfo(): array
    {
        $moduleInfoObj = $this->getDi()[\CentreonLegacy\ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION];
        $modules = $moduleInfoObj->getList();

        if (
            array_key_exists('centreon-bam-server', $modules)
            && $modules['centreon-bam-server']['is_installed']
        ) {
            return ['enabled' => true];
        }

        return ['enabled' => false];
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
    public function authorize($action, $user, $isInternal = false): bool
    {
        return true;
    }

    /**
     * Name of web service object.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_modules_webservice';
    }
}
