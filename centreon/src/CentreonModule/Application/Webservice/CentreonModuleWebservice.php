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

namespace CentreonModule\Application\Webservice;

use Centreon\Application\DataRepresenter\Bulk;
use Centreon\Application\DataRepresenter\Response;
use Centreon\Infrastructure\Webservice;
use CentreonModule\Application\DataRepresenter\ModuleDetailEntity;
use CentreonModule\Application\DataRepresenter\ModuleEntity;
use CentreonModule\Application\DataRepresenter\UpdateAction;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;

/**
 * @OA\Tag(name="centreon_module", description="Resource for authorized access")
 */
class CentreonModuleWebservice extends Webservice\WebServiceAbstract implements Webservice\WebserviceAutorizeRestApiInterface
{
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
        return ! (! $user->admin && $user->access->page('50709') === 0);
    }

    /**
     * @OA\Get(
     *   path="/internal.php?object=centreon_module&action=list",
     *   description="Get list of modules and widgets",
     *   tags={"centreon_module"},
     *   security={{"Session": {}}},
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_module"},
     *          default="centreon_module"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"list"},
     *          default="list"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="search",
     *
     *       @OA\Schema(
     *          type="string"
     *       ),
     *       description="filter the result by name and keywords",
     *       required=false
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="installed",
     *
     *       @OA\Schema(
     *          type="boolean"
     *       ),
     *       description="filter the result by installed or non-installed modules",
     *       required=false
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="updated",
     *
     *       @OA\Schema(
     *          type="boolean"
     *       ),
     *       description="filter the result by updated or non-installed modules",
     *       required=false
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="types",
     *
     *       @OA\Schema(
     *          type="array",
     *          items={"type": "string", "enum": {"module", "widget"}}
     *       ),
     *       description="filter the result by type",
     *       required=false
     *   ),
     *
     *   @OA\Response(
     *      response="200",
     *      description="OK",
     *
     *       @OA\MediaType(
     *          mediaType="application/json",
     *
     *          @OA\Schema(
     *
     *              @OA\Property(property="module",
     *                  @OA\Property(
     *                      property="entities",
     *                      type="array",
     *
     *                      @OA\Items(ref="#/components/schemas/ModuleEntity")
     *                  ),
     *
     *                  @OA\Property(
     *                      property="pagination",
     *                      ref="#/components/schemas/Pagination"
     *                  )
     *              ),
     *              @OA\Property(property="widget", type="object",
     *                  @OA\Property(
     *                      property="entities",
     *                      type="array",
     *
     *                      @OA\Items(ref="#/components/schemas/ModuleEntity")
     *                  ),
     *
     *                  @OA\Property(
     *                      property="pagination",
     *                      ref="#/components/schemas/Pagination"
     *                  )
     *              ),
     *              @OA\Property(property="status", type="boolean")
     *          )
     *      )
     *   )
     * )
     *
     * Get list of modules and widgets
     *
     * @throws \RestBadRequestException
     *
     * @return Response
     */
    public function getList()
    {
        // extract post payload
        $request = $this->query();

        $search = isset($request['search']) && $request['search'] ? $request['search'] : null;
        $installed = $request['installed'] ?? null;
        $updated = $request['updated'] ?? null;
        $typeList = isset($request['types']) ? (array) $request['types'] : null;

        if ($installed && strtolower((string) $installed) === 'true') {
            $installed = true;
        } elseif ($installed && strtolower((string) $installed) === 'false') {
            $installed = false;
        } elseif ($installed) {
            $installed = null;
        }

        if ($updated && strtolower((string) $updated) === 'true') {
            $updated = true;
        } elseif ($updated && strtolower((string) $updated) === 'false') {
            $updated = false;
        } elseif ($updated) {
            $updated = null;
        }

        $list = $this->getDi()[ServiceProvider::CENTREON_MODULE]
            ->getList($search, $installed, $updated, $typeList);

        $result = new Bulk($list, null, null, null, ModuleEntity::class);

        return new Response($result);
    }

    /**
     * @OA\Get(
     *   path="/internal.php?object=centreon_module&action=details",
     *   description="Get details of modules and widgets",
     *   tags={"centreon_module"},
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_module"},
     *          default="centreon_module"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"details"},
     *          default="details"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="id",
     *
     *       @OA\Schema(
     *          type="string"
     *       ),
     *       description="ID of a module or a widget",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="type",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={
     *              "module",
     *              "widget"
     *          }
     *       ),
     *       description="type of object",
     *       required=true
     *   ),
     *
     *   @OA\Response(
     *      response="200",
     *      description="OK",
     *
     *       @OA\MediaType(
     *          mediaType="application/json",
     *
     *          @OA\Schema(
     *
     *              @OA\Property(property="result", ref="#/components/schemas/ModuleDetailEntity"),
     *              @OA\Property(property="status", type="boolean")
     *          )
     *      )
     *   )
     * )
     *
     * Get details of module/widget
     *
     * @throws \RestBadRequestException
     *
     * @return Response
     */
    public function getDetails()
    {
        // extract post payload
        $request = $this->query();

        $id = isset($request['id']) && $request['id'] ? $request['id'] : null;
        $type = $request['type'] ?? null;

        $detail = $this->getDi()[ServiceProvider::CENTREON_MODULE]
            ->getDetail($id, $type);

        $result = null;
        $status = false;

        if ($detail !== null) {
            $result = new ModuleDetailEntity($detail);
            $status = true;
        }

        return new Response($result, $status);
    }

    /**
     * @OA\Post(
     *   path="/internal.php?object=centreon_module&action=install",
     *   summary="Install module or widget",
     *   tags={"centreon_module"},
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_module"},
     *          default="centreon_module"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"install"},
     *          default="install"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="id",
     *
     *       @OA\Schema(
     *          type="string"
     *       ),
     *       description="ID of a module or a widget",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="type",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={
     *              "module",
     *              "widget"
     *          }
     *       ),
     *       description="type of object",
     *       required=true
     *   ),
     *
     *   @OA\Response(
     *       response="200",
     *       description="OK",
     *
     *       @OA\JsonContent(
     *
     *          @OA\Property(property="result", ref="#/components/schemas/UpdateAction"),
     *          @OA\Property(property="status", type="boolean")
     *       )
     *   )
     * )
     *
     * Install module or widget
     *
     * @throws \RestBadRequestException
     *
     * @return Response
     */
    public function postInstall()
    {
        // extract post payload
        $request = $this->query();

        $id = isset($request['id']) && $request['id'] ? $request['id'] : '';
        $type = $request['type'] ?? '';

        $status = false;
        $result = null;
        $entity = null;

        /**
         * @var CentreonModuleService
         */
        $moduleService = $this->getDi()[ServiceProvider::CENTREON_MODULE];

        try {
            $entity = $moduleService->install($id, $type);
        } catch (\Exception $e) {
            $result = new UpdateAction(null, $e->getMessage());
        }

        if ($entity !== null) {
            $result = new UpdateAction($entity);
            $status = true;
        }

        return new Response($result, $status);
    }

    /**
     * @OA\Post(
     *   path="/internal.php?object=centreon_module&action=update",
     *   summary="Update module or widget",
     *   tags={"centreon_module"},
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_module"},
     *          default="centreon_module"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"update"},
     *          default="update"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="id",
     *
     *       @OA\Schema(
     *          type="string"
     *       ),
     *       description="ID of a module or a widget",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="type",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={
     *              "module",
     *              "widget"
     *          }
     *       ),
     *       description="type of object",
     *       required=true
     *   ),
     *
     *   @OA\Response(
     *       response="200",
     *       description="OK",
     *
     *       @OA\JsonContent(
     *
     *          @OA\Property(property="result", ref="#/components/schemas/UpdateAction"),
     *          @OA\Property(property="status", type="boolean")
     *       )
     *   )
     * )
     *
     * Update module or widget
     *
     * @throws \RestBadRequestException
     *
     * @return Response
     */
    public function postUpdate()
    {
        // extract post payload
        $request = $this->query();

        $id = isset($request['id']) && $request['id'] ? $request['id'] : '';
        $type = $request['type'] ?? '';

        $status = false;
        $result = null;
        $entity = null;

        try {
            $entity = $this->getDi()[ServiceProvider::CENTREON_MODULE]
                ->update($id, $type);
        } catch (\Exception $e) {
            $result = new UpdateAction(null, $e->getMessage());
        }

        if ($entity !== null) {
            $result = new UpdateAction($entity);
            $status = true;
        }

        return new Response($result, $status);
    }

    /**
     * @OA\Delete(
     *   path="/internal.php?object=centreon_module&action=remove",
     *   summary="Remove module or widget",
     *   tags={"centreon_module"},
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_module"},
     *          default="centreon_module"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"remove"},
     *          default="remove"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="id",
     *
     *       @OA\Schema(
     *          type="string"
     *       ),
     *       description="ID of a module or a widget",
     *       required=true
     *   ),
     *
     *   @OA\Parameter(
     *       in="query",
     *       name="type",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={
     *              "module",
     *              "widget"
     *          }
     *       ),
     *       description="type of object",
     *       required=true
     *   ),
     *
     *   @OA\Response(
     *       response="200",
     *       description="OK",
     *
     *       @OA\JsonContent(
     *
     *          @OA\Property(property="result", type="string"),
     *          @OA\Property(property="status", type="boolean")
     *       )
     *   )
     * )
     *
     * Remove module or widget
     *
     * @throws \RestBadRequestException
     *
     * @return Response
     */
    public function deleteRemove()
    {
        // extract post payload
        $request = $this->query();

        $id = isset($request['id']) && $request['id'] ? $request['id'] : '';
        $type = $request['type'] ?? '';

        $status = false;
        $result = null;

        try {
            $this->getDi()[ServiceProvider::CENTREON_MODULE]
                ->remove($id, $type);

            $status = true;
        } catch (\Exception $e) {
            $result = $e->getMessage();
        }

        return new Response($result, $status);
    }

    /**
     * Name of web service object.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_module';
    }
}
