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

namespace Centreon\Application\Webservice;

use Centreon\Infrastructure\Webservice;
use Centreon\Infrastructure\Webservice\WebserviceAutorizePublicInterface;
use Centreon\ServiceProvider;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;

class CentreonFrontendComponent extends Webservice\WebServiceAbstract implements WebserviceAutorizePublicInterface
{
    /** @var \Psr\Container\ContainerInterface */
    protected $services;

    /**
     * Name of web service object
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_frontend_component';
    }

    /**
     * @SWG\Get(
     *   path="/centreon/api/internal.php",
     *   operationId="getComponents",
     *   @SWG\Parameter(
     *       in="query",
     *       name="object",
     *       type="string",
     *       description="the name of the API object class",
     *       required=true,
     *       enum="centreon_configuration_remote",
     *   ),
     *   @SWG\Parameter(
     *       in="query",
     *       name="action",
     *       type="string",
     *       description="the name of the action in the API class",
     *       required=true,
     *       enum="components",
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="JSON with the external react components (pages, hooks)"
     *   )
     * )
     *
     * Get list with remote components
     *
     * @return array
     * @example [
     *            ['pages' => [
     *              '/my/module/route' => [
     *                'js' => '<my_module_path>/static/pages/my/module/route/index.js',
     *                'css' => '<my_module_path>/static/pages/my/module/route/index.css'
     *              ]
     *            ]],
     *            ['hooks' => [
     *              '/header/topCounter' => [
     *                [
     *                  'js' => '<my_module_path>/static/hooks/header/topCounter/index.js',
     *                  'css' => '<my_module_path>/static/hooks/header/topCounter/index.css'
     *                ]
     *              ]
     *            ]]
     *          ]
     */
    public function getComponents(): array
    {
        $service = $this->services->get(ServiceProvider::CENTREON_FRONTEND_COMPONENT_SERVICE);

        return [
            'pages' => $service->getPages(),
            'hooks' => $service->getHooks(),
        ];
    }

    /**
     * Extract services that are in use only
     *
     * @param Container $di
     */
    public function setDi(Container $di): void
    {
        $this->services = new ServiceLocator($di, [
            ServiceProvider::CENTREON_FRONTEND_COMPONENT_SERVICE,
        ]);
    }
}
