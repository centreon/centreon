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

declare(strict_types=1);

namespace Core\ResourceAccess\Infrastructure\API\FindRule;

use Centreon\Application\Controller\AbstractController;
use Core\ResourceAccess\Application\UseCase\FindRule\FindRule;
use Core\ResourceAccess\Application\UseCase\FindRule\FindRulePresenterInterface;
use Symfony\Component\HttpFoundation\Response;

final class FindRuleController extends AbstractController
{
    /**
     * @param FindRule $useCase
     * @param FindRulePresenterInterface $presenter
     * @param int $ruleId
     *
     * @return Response
     */
    public function __invoke(
        int $ruleId,
        FindRule $useCase,
        FindRulePresenterInterface $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($ruleId, $presenter);

        return $presenter->show();
    }
}

