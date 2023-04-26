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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Api\UpdateSAMLConfiguration;

use Centreon\Domain\Contact\Contact;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Centreon\Application\Controller\AbstractController;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration\{
    UpdateSAMLConfiguration,
    UpdateSAMLConfigurationPresenterInterface,
    UpdateSAMLConfigurationRequest
};

class UpdateSAMLConfigurationController extends AbstractController
{
    /**
     * @param UpdateSAMLConfiguration $useCase
     * @param Request $request
     * @param UpdateSAMLConfigurationPresenterInterface $presenter
     * @return object
     */
    public function __invoke(
        UpdateSAMLConfiguration $useCase,
        Request $request,
        UpdateSAMLConfigurationPresenterInterface $presenter
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->hasTopologyRole(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_READ_WRITE)) {
            return $this->view(null, Response::HTTP_FORBIDDEN);
        }
        $this->validateDataSent($request, __DIR__ . '/UpdateSAMLConfigurationSchema.json');
        $updateRequest = $this->createUpdateSAMLConfigurationRequest($request);
        $useCase($presenter, $updateRequest);

        return $presenter->show();
    }

    /**
     * @param Request $request
     * @return UpdateSAMLConfigurationRequest
     */
    private function createUpdateSAMLConfigurationRequest(Request $request): UpdateSAMLConfigurationRequest
    {
        $json = (string) $request->getContent();
        $requestData  = json_decode($json, true);
        $updateRequest = new UpdateSAMLConfigurationRequest();
        $updateRequest->isActive = $requestData['is_active'];
        $updateRequest->isForced = $requestData['is_forced'];
        $updateRequest->entityIdUrl = $requestData['entity_id_url'];
        $updateRequest->remoteLoginUrl = $requestData['remote_login_url'];
        $updateRequest->publicCertificate = $requestData['certificate'];
        $updateRequest->userIdAttribute = $requestData['user_id_attribute'];
        $updateRequest->logoutFrom = $requestData['logout_from'];
        $updateRequest->logoutFromUrl = $requestData['logout_from_url'];
        $updateRequest->isAutoImportEnabled = $requestData['auto_import'];
        $updateRequest->contactTemplate = $requestData['contact_template'];
        $updateRequest->emailBindAttribute = $requestData['email_bind_attribute'];
        $updateRequest->userNameBindAttribute = $requestData['fullname_bind_attribute'];
        $updateRequest->rolesMapping = $requestData['roles_mapping'];
        $updateRequest->authenticationConditions = $requestData["authentication_conditions"];
        $updateRequest->groupsMapping = $requestData["groups_mapping"];

        return $updateRequest;
    }
}
