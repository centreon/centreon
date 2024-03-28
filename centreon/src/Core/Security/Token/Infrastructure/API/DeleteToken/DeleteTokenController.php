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

namespace Core\Security\Token\Infrastructure\API\DeleteToken;

use Centreon\Application\Controller\AbstractController;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\Token\Application\UseCase\DeleteToken\DeleteToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DeleteTokenController extends AbstractController
{
    /**
     * @param DefaultPresenter $presenter
     * @param DeleteToken $useCase
     * @param string $tokenName
     * @param int $userId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        DefaultPresenter $presenter,
        DeleteToken $useCase,
        string $tokenName,
        ?int $userId = null
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($presenter, $tokenName, $userId);

        return $presenter->show();
    }
}
