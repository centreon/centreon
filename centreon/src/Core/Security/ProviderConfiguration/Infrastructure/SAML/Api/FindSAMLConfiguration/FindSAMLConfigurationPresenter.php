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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Api\FindSAMLConfiguration;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\FindSAMLConfiguration\{
    FindSAMLConfigurationPresenterInterface,
    FindSAMLConfigurationResponse
};

class FindSAMLConfigurationPresenter extends AbstractPresenter implements FindSAMLConfigurationPresenterInterface
{
    /**
     * {@inheritDoc}
     * @param FindSAMLConfigurationResponse $data
     */
    public function present(mixed $data): void
    {
        $presenterResponse = [
            'is_active' => $data->isActive,
            'is_forced' => $data->isForced,
            'entity_id_url' => $data->entityIdUrl,
            'remote_login_url' => $data->remoteLoginUrl,
            'certificate' => $data->publicCertificate,
            'user_id_attribute' => $data->userIdAttribute,
            'logout_from' => $data->logoutFrom,
            'logout_from_url' => $data->logoutFromUrl,
            'auto_import' => $data->isAutoImportEnabled,
            'contact_template' => $data->contactTemplate,
            'email_bind_attribute' => $data->emailBindAttribute,
            'fullname_bind_attribute' => $data->userNameBindAttribute,
            'roles_mapping' => $data->aclConditions,
            'authentication_conditions' => $data->authenticationConditions,
            'groups_mapping' => $data->groupsMapping
        ];

        parent::present($presenterResponse);
    }
}
