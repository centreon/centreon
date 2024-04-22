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

namespace Core\Contact\Application\UseCase\FindContactTemplates;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Exception\ContactTemplateException;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;

class FindContactTemplates
{
    use LoggerTrait;

    /**
     * @param ReadContactTemplateRepositoryInterface $repository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadContactTemplateRepositoryInterface $repository,
        readonly private ContactInterface $user,
    ) {
    }

    /**
     * @param FindContactTemplatesPresenterInterface $presenter
     */
    public function __invoke(FindContactTemplatesPresenterInterface $presenter): void
    {
        try {
            $this->info('Find contact templates', ['user_id' => $this->user->getId()]);
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_CONTACT_TEMPLATES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_CONTACT_TEMPLATES_READ_WRITE)
            ) {

                $this->error('User doesn\'t have sufficient right to list contact templates', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ContactTemplateException::listingNotAllowed())
                );

                return;
            }

            $presenter->present(new FindContactTemplatesResponse($this->repository->findAll()));
        } catch (\Throwable $ex) {
            $this->error('Error while searching for contact templates', [
                'trace' => $ex->getTraceAsString(),
            ]);
            $presenter->setResponseStatus(
                new ErrorResponse(ContactTemplateException::errorWhileSearchingForContactTemplate())
            );

            return;
        }
    }
}
