<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Api\FindSAMLConfiguration;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\FindSAMLConfiguration\{
    FindSAMLConfigurationPresenterInterface,
    FindSAMLConfigurationResponse
};

class FindSAMLConfigurationPresenter extends AbstractPresenter implements FindSAMLConfigurationPresenterInterface
{
    public function presentResponse(FindSAMLConfigurationResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                ExceptionLogger::create()->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }

        $this->present([
            'is_active' => $response->isActive,
            'is_forced' => $response->isForced,
            'entity_id_url' => $response->entityIdUrl,
            'remote_login_url' => $response->remoteLoginUrl,
            'certificate' => $response->publicCertificate,
            'user_id_attribute' => $response->userIdAttribute,
            'requested_authn_context' => $response->requestAuthnContext,
            'requested_authn_context_comparison' => $response->requestedAuthnContextComparison->value,
            'logout_from' => $response->logoutFrom,
            'logout_from_url' => $response->logoutFromUrl,
            'auto_import' => $response->isAutoImportEnabled,
            'contact_template' => $response->contactTemplate,
            'email_bind_attribute' => $response->emailBindAttribute,
            'fullname_bind_attribute' => $response->userNameBindAttribute,
            'roles_mapping' => $response->aclConditions,
            'authentication_conditions' => $response->authenticationConditions,
            'groups_mapping' => $response->groupsMapping,
        ]);
    }
}
