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

namespace Core\Platform\Infrastructure\API\FindFeatures;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Platform\Application\UseCase\FindFeatures\FindFeatures;
use Core\Platform\Application\UseCase\FindFeatures\FindFeaturesPresenterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class FindFeaturesController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param FindFeatures $useCase
     * @param FindFeaturesPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        FindFeatures $useCase,
        FindFeaturesPresenterInterface $presenter,
    ): Response {
        // This endpoint is public.

        $useCase($presenter);

        return $presenter->show();
    }
}
