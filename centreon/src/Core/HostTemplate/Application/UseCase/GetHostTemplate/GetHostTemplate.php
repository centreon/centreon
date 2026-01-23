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

declare(strict_types=1);

namespace Core\HostTemplate\Application\UseCase\GetHostTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Host\Application\Converter\HostEventConverter;

final class GetHostTemplate
{
    use LoggerTrait;

     /** @var AccessGroup[] */
    private array $accessGroups;

    /**
     * Summary of __construct
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param ReadHostCategoryRepositoryInterface $readHostCategoryRepository
     * @param ContactInterface $user
     * @param ReadHostMacroRepositoryInterface $readHostMacroRepository
     */
    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ContactInterface $user,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
    ) {
    }

    /**
     * @param GetHostTemplatePresenterInterface $presenter
     * @param int $hostTemplateId
     */
    public function __invoke(GetHostTemplatePresenterInterface $presenter, int $hostTemplateId): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see host templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(HostTemplateException::accessNotAllowed())
                );

                return;
            }

            $hostTemplate = null;
            $hostCategories = [];
            $macros = [];
            $parentTemplates = [];
            if ($this->user->isAdmin()) {
                $hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId);
                $hostCategories = $this->readHostCategoryRepository->findByHost($hostTemplateId);
                $macros = $this->readHostMacroRepository->findByHostId($hostTemplateId);
                $parentTemplates = $this->findParentTemplates($hostTemplateId);
            } else {

                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $hostTemplate = $this->readHostTemplateRepository->findByIdAndAccessGroups($hostTemplateId, $this->accessGroups);
                $hostCategories = $this->readHostCategoryRepository->findByHostAndAccessGroups(
                        $hostTemplateId,
                        $this->accessGroups
                );
                $macros = $this->readHostMacroRepository->findByHostId($hostTemplateId);
                $parentTemplates = $this->findParentTemplates($hostTemplateId);
            }

            if (! $hostTemplate) {
                 $this->error(
                    'Host template not found',
                    ['host_template_id' => $hostTemplateId]
                );
                $presenter->presentResponse(new NotFoundResponse('HostTemplate'));

                return;
            }

            $presenter->presentResponse($this->createResponse($hostTemplate, $hostCategories, $parentTemplates,  $macros));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(HostTemplateException::errorWhileRetrievingObject())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param HostTemplate $hostTemplate
     * @param HostCategory[] $hostCategories
     * @param array<array{id:int,name:string}> $parentTemplates
     * @param Macro[] $macros
     *
     * @throws \Throwable
     *
     *
     * @return GetHostTemplateResponse
     */
    private function createResponse(HostTemplate $hostTemplate, array $hostCategories, array $parentTemplates, array $macros): GetHostTemplateResponse
    {


        $response = new GetHostTemplateResponse();

        $response->id = $hostTemplate->getId();
        $response->name = $hostTemplate->getName();
        $response->alias = $hostTemplate->getAlias();
        $response->snmpVersion = $hostTemplate->getSnmpVersion()?->value;
        $response->snmpCommunity = $hostTemplate->getSnmpCommunity();
        $response->timezoneId = $hostTemplate->getTimezoneId();
        $response->severityId = $hostTemplate->getSeverityId();
        $response->checkCommandId = $hostTemplate->getCheckCommandId();
        $response->checkCommandArgs = $hostTemplate->getCheckCommandArgs();
        $response->checkTimeperiodId = $hostTemplate->getCheckTimeperiodId();
        $response->maxCheckAttempts = $hostTemplate->getMaxCheckAttempts();
        $response->normalCheckInterval = $hostTemplate->getNormalCheckInterval();
        $response->retryCheckInterval = $hostTemplate->getRetryCheckInterval();
        $response->activeCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getActiveCheckEnabled());
        $response->passiveCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getPassiveCheckEnabled());
        $response->notificationEnabled = YesNoDefaultConverter::toInt($hostTemplate->getNotificationEnabled());
        $response->notificationOptions = HostEventConverter::toBitFlag($hostTemplate->getNotificationOptions());
        $response->notificationInterval = $hostTemplate->getNotificationInterval();
        $response->notificationTimeperiodId = $hostTemplate->getNotificationTimeperiodId();
        $response->addInheritedContactGroup = $hostTemplate->addInheritedContactGroup();
        $response->addInheritedContact = $hostTemplate->addInheritedContact();
        $response->firstNotificationDelay = $hostTemplate->getFirstNotificationDelay();
        $response->recoveryNotificationDelay = $hostTemplate->getRecoveryNotificationDelay();
        $response->acknowledgementTimeout = $hostTemplate->getAcknowledgementTimeout();
        $response->freshnessChecked = YesNoDefaultConverter::toInt($hostTemplate->getFreshnessChecked());
        $response->freshnessThreshold = $hostTemplate->getFreshnessThreshold();
        $response->flapDetectionEnabled = YesNoDefaultConverter::toInt($hostTemplate->getFlapDetectionEnabled());
        $response->lowFlapThreshold = $hostTemplate->getLowFlapThreshold();
        $response->highFlapThreshold = $hostTemplate->getHighFlapThreshold();
        $response->eventHandlerEnabled = YesNoDefaultConverter::toInt($hostTemplate->getEventHandlerEnabled());
        $response->eventHandlerCommandId = $hostTemplate->getEventHandlerCommandId();
        $response->eventHandlerCommandArgs = $hostTemplate->getEventHandlerCommandArgs();
        $response->noteUrl = $hostTemplate->getNoteUrl();
        $response->note = $hostTemplate->getNote();
        $response->actionUrl = $hostTemplate->getActionUrl();
        $response->iconId = $hostTemplate->getIconId();
        $response->iconAlternative = $hostTemplate->getIconAlternative();
        $response->comment = $hostTemplate->getComment();
        $response->isLocked = $hostTemplate->isLocked();

        $response->categories = array_map(
            fn (HostCategory $category) => ['id' => $category->getId(), 'name' => $category->getName()],
            $hostCategories
        );

        $response->templates = array_map(
            fn ($template) => ['id' => $template['id'], 'name' => $template['name']],
            $parentTemplates
        );

        $response->macros = array_map(
            static fn (Macro $macro): array => [
                'id' => $macro->getId(),
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ],
            $macros
        );


        return $response;
    }


     /**
     * @param int $hostTemplateId
     *
     * @throws HostTemplateException
     * @throws \Throwable
     *
     * @return array<array{id:int,name:string}>
     */
    private function findParentTemplates(int $hostTemplateId): array
    {

        $templateIds = $this->readHostTemplateRepository->findByHostId($hostTemplateId);
        $templateNames = $this->readHostTemplateRepository->findNamesByIds($templateIds);

        $parentTemplates = [];
        foreach ($templateIds as $templateId) {
            $parentTemplates[] = [
                'id' => $templateId,
                'name' => $templateNames[$templateId],
            ];
        }

        return $parentTemplates;
    }
}