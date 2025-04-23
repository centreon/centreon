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

namespace Core\HostTemplate\Application\UseCase\DeleteHostTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;

final class DeleteHostTemplate
{
    use LoggerTrait,VaultTrait;

    public function __construct(
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ContactInterface $user,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
    }

    /**
     * @param int $hostTemplateId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $hostTemplateId, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to delete host templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostTemplateException::deleteNotAllowed())
                );

                return;
            }
            if (! ($hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId))) {
                $this->error(
                    'Host template not found',
                    ['host_template_id' => $hostTemplateId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Host template'));

                return;
            }
            if ($this->readHostTemplateRepository->isLocked($hostTemplateId)) {
                $this->error(
                    'Host template is locked, deletion refused.',
                    ['host_template_id' => $hostTemplateId]
                );
                $presenter->setResponseStatus(
                    new InvalidArgumentResponse(HostTemplateException::hostIsLocked($hostTemplateId))
                );

                return;
            }

            if ($this->writeVaultRepository->isVaultConfigured()) {
                $this->retrieveHostTemplateUuidFromVault($hostTemplate);
                if ($this->uuid !== null) {
                    $this->writeVaultRepository->delete($this->uuid);
                }
            }

            $this->writeHostTemplateRepository->delete($hostTemplateId);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostTemplateException::deleteHostTemplate())
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     */
    private function retrieveHostTemplateUuidFromVault(HostTemplate $hostTemplate): void
    {
        $this->uuid = $this->getUuidFromPath($hostTemplate->getSnmpCommunity());
        if (null === $this->uuid) {
            $macros = $this->readHostMacroRepository->findByHostId($hostTemplate->getId());
            foreach ($macros as $macro) {
                if (
                    $macro->isPassword() === true
                    && null !== ($this->uuid = $this->getUuidFromPath($macro->getValue()))
                ) {

                    break;
                }
            }
        }
    }
}
