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

namespace Core\User\Application\UseCase\FindCurrentUserParameters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Configuration\User\Exception\UserException;
use Core\User\Domain\Model\UserInterfaceDensity;
use Core\User\Domain\Model\UserTheme;

final class FindCurrentUserParameters
{
    use LoggerTrait;

    public function __construct()
    {
    }

    public function __invoke(
        Contact $user,
        FindCurrentUserParametersPresenterInterface $presenter
    ): void
    {
        try {
            $response = $this->createResponse($user);

            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(UserException::errorWhileSearchingForUser($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    public function createResponse(Contact $user): FindCurrentUserParametersResponse
    {
        $dto = new FindCurrentUserParametersResponse();

        $dto->id = $user->getId();
        $dto->name = $user->getName();
        $dto->alias = $user->getAlias();
        $dto->email = $user->getEmail();
        $dto->timezone = $user->getTimezone()->getName();
        $dto->locale = $user->getLocale();
        $dto->isAdmin = $user->isAdmin();
        $dto->useDeprecatedPages = $user->isUsingDeprecatedPages();
        $dto->isExportButtonEnabled = $this->hasExportButtonRole($user);
        $dto->theme = UserTheme::tryFrom((string) $user->getTheme())
            ?? UserTheme::Light;
        $dto->userInterfaceDensity = UserInterfaceDensity::tryFrom((string) $user->getUserInterfaceDensity())
            ?? UserInterfaceDensity::Compact;
        $dto->defaultPage = $user->getDefaultPage()?->getRedirectionUri();

        return $dto;
    }

    private function hasExportButtonRole(Contact $user): bool
    {

        return $user->isAdmin() || $user->hasRole(Contact::ROLE_GENERATE_CONFIGURATION);
    }
}
