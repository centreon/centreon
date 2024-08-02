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

namespace Core\AdditionalConnectorConfiguration\Application\UseCase\FindPollers;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindPollers
{
    use LoggerTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        string $strType,
        FindPollersPresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ACC_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to access additional connector configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(AccException::accessNotAllowed())
                );

                return;
            }

            $type = Type::from($strType);

            if ($this->user->isAdmin()) {
                $pollers = $this->readAccRepository->findAvailablePollersByType($type, $this->requestParameters);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $pollers = $this->readAccRepository->findAvailablePollersByTypeAndAccessGroup($type, $accessGroups, $this->requestParameters);
            }

            $presenter->presentResponse(new FindPollersResponse($pollers));
        } catch (\ValueError $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof AccException
                    ? $ex
                    : AccException::findPollers($strType)
            ));
        }
    }
}
