<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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
declare(strict_types=1);

namespace Centreon\Application\Controller\Configuration;

use FOS\RestBundle\Context\Context;
use FOS\RestBundle\View\View;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\Configuration\Icon\Interfaces\IconServiceInterface;
use Centreon\Application\Controller\AbstractController;

/**
 * This class is design to manage all API REST requests concerning the icons configuration.
 *
 * @package Centreon\Application\Controller\Configuration
 */
class IconController extends AbstractController
{
    // Groups for serializing
    public const SERIALIZER_GROUPS_MAIN = ['icon_main'];

    /**
     * @var IconServiceInterface
     */
    private $iconService;

    public function __construct(IconServiceInterface $iconService)
    {
        $this->iconService = $iconService;
    }

    /**
     * Get list of icons
     *
     * @param RequestParametersInterface $requestParameters
     * @return View
     * @throws \Exception
     */
    public function getIcons(RequestParametersInterface $requestParameters): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $icons = $this->iconService->getIcons();
        foreach ($icons as $icon) {
            if (isset($_SERVER['REQUEST_URI']) && preg_match('/^(.+)\/api\/.+/', $_SERVER['REQUEST_URI'], $matches)) {
                $icon->setUrl($matches[1] . '/img/media/' . $icon->getUrl());
            }
        }

        $context = (new Context())
            ->setGroups(self::SERIALIZER_GROUPS_MAIN);

        return $this->view([
            'result' => $icons,
            'meta' => $requestParameters->toArray(),
        ])->setContext($context);
    }
}
