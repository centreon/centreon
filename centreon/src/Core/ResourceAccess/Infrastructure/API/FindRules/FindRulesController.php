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

declare(strict_types=1);

namespace Core\ResourceAccess\Infrastructure\API\FindRules;

use Centreon\Application\Controller\AbstractController;
use Core\ResourceAccess\Application\UseCase\FindRules\FindRules;
use Core\ResourceAccess\Application\UseCase\FindRules\FindRulesPresenterInterface;
use Symfony\Component\HttpFoundation\Response;

final class FindRulesController extends AbstractController
{
    /**
     * @param FindRules $useCase
     * @param FindRulesPresenterInterface $presenter
     *
     * @return Response
     */
    public function __invoke(
        FindRules $useCase,
        FindRulesPresenterInterface $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($presenter);

        return $presenter->show();
    }
}
