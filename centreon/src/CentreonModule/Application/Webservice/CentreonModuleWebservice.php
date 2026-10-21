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

namespace CentreonModule\Application\Webservice;

use Centreon\Application\DataRepresenter\Bulk;
use Centreon\Application\DataRepresenter\Response;
use Centreon\Infrastructure\Webservice;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Application\DataRepresenter\ModuleDetailEntity;
use CentreonModule\Application\DataRepresenter\ModuleEntity;
use CentreonModule\Application\DataRepresenter\UpdateAction;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\Infrastructure\Source\ModuleSource;
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

        if ($installed && strtolower($installed) === 'true') {
            $installed = true;
        } elseif ($installed && strtolower($installed) === 'false') {
            $installed = false;
        } elseif ($installed) {
            $installed = null;
        }

        if ($updated && strtolower($updated) === 'true') {
            $updated = true;
        } elseif ($updated && strtolower($updated) === 'false') {
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
     *   @OA\Parameter(
     *       in="query",
     *       name="clearCache",
     *
     *       @OA\Schema(
     *          type="string",
     *          enum={"0", "1"},
     *          default="1"
     *       ),
     *       description="0 to leave the cache as is when updating several modules: the clearCache action ends the batch",
     *       required=false
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

        /** @var CentreonModuleService $moduleService */
        $moduleService = $this->getDi()[ServiceProvider::CENTREON_MODULE];
        /** @var CacheClearer $cacheClearer */
        $cacheClearer = $this->getDi()[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER];

        try {
            // One clear for the module and its dependencies.
            $entity = $cacheClearer->suspended(static fn () => $moduleService->update($id, $type));
        } catch (\Throwable $e) {
            $result = new UpdateAction(null, $e->getMessage());
        }

        // Even after a failure, for the dependencies already upgraded. A batch passes clearCache=0, then calls clearCache.
        if ($type === ModuleSource::TYPE && ($request['clearCache'] ?? '1') !== '0') {
            try {
                $cacheClearer->clear();
            } catch (\Throwable $e) {
                // Still a failure, until the cache is cleared, but not the one of the update.
                $result ??= new UpdateAction(null, $entity !== null ? 'The module was updated, but the cache could not be cleared: ' . $e->getMessage() : $e->getMessage());
            }
        }

        if ($entity !== null && $result === null) {
            $result = new UpdateAction($entity);
            $status = true;
        }

        return new Response($result, $status);
    }

    /**
     * @OA\Post(
     *   path="/internal.php?object=centreon_module&action=clearCache",
     *   description="Clear the cache once a batch of module updates sent with clearCache=0 is over, whatever their outcome",
     *   tags={"centreon_module"},
     *   security={{"Session": {}}},
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
     * @return Response
     */
    public function postClearCache()
    {
        try {
            $this->getDi()[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER]->clear();
        } catch (\Throwable $e) {
            return new Response(new UpdateAction(null, $e->getMessage()), false);
        }

        return new Response(null);
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
