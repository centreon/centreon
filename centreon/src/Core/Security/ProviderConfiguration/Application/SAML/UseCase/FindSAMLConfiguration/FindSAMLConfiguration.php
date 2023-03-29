<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Security\ProviderConfiguration\Application\SAML\UseCase\FindSAMLConfiguration;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;

class FindSAMLConfiguration
{
    use LoggerTrait;

    /**
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     */
    public function __construct(private ProviderAuthenticationFactoryInterface $providerFactory)
    {
    }

    /**
     * @param FindSAMLConfigurationPresenterInterface $presenter
     */
    public function __invoke(FindSAMLConfigurationPresenterInterface $presenter): void
    {
        try {
            $provider = $this->providerFactory->create(Provider::SAML);
            $configuration = $provider->getConfiguration();
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
            return;
        }

        $presenter->present($this->createResponse($configuration));
    }

    /**
     * @param Configuration $provider
     * @return FindSAMLConfigurationResponse
     */
    private function createResponse(Configuration $provider): FindSAMLConfigurationResponse
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $provider->getCustomConfiguration();
        $response = new FindSAMLConfigurationResponse();
        $response->isActive = $provider->isActive();
        $response->isForced = $provider->isForced();
        $response->entityIdUrl = $customConfiguration->getEntityIDUrl();
        $response->remoteLoginUrl = $customConfiguration->getRemoteLoginUrl();
        $response->publicCertificate = $customConfiguration->getPublicCertificate();
        $response->userIdAttribute = $customConfiguration->getUserIdAttribute();
        $response->logoutFrom = $customConfiguration->getLogoutFrom();
        $response->logoutFromUrl = $customConfiguration->getLogoutFromUrl();
        $response->isAutoImportEnabled = $customConfiguration->isAutoImportEnabled();
        $response->emailBindAttribute = $customConfiguration->getEmailBindAttribute();
        $response->userNameBindAttribute = $customConfiguration->getUserNameBindAttribute();
        $response->contactTemplate = $customConfiguration->getContactTemplate() === null
            ? null
            : $response::contactTemplateToArray($customConfiguration->getContactTemplate());
        $response->aclConditions = FindSAMLConfigurationResponse::aclConditionsToArray(
            $customConfiguration->getACLConditions()
        );
        $response->authenticationConditions =
            $response::authenticationConditionsToArray(
                $customConfiguration->getAuthenticationConditions()
            );
        $response->groupsMapping = $response::groupsMappingToArray(
            $customConfiguration->getGroupsMapping()
        );

        return $response;
    }
}
