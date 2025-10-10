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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Api\UpdateSAMLConfiguration;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Log\Logger;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration\{UpdateSAMLConfiguration,
    UpdateSAMLConfigurationRequest};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdateSAMLConfigurationController extends AbstractController
{
    public function __invoke(
        UpdateSAMLConfiguration $useCase,
        Request $request,
        UpdateSAMLConfigurationPresenter $presenter,
    ): object {
        try {
            /** @var Contact $contact */
            $contact = $this->getUser();
        } catch (\LogicException $e) {
            ExceptionLogger::create()->log($e);
            $presenter->setResponseStatus(
                new ErrorResponse('User not found when trying to update SAML configuration.')
            );

            return $presenter->show();
        }

        if (! $contact->hasTopologyRole(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_READ_WRITE)) {
            Logger::create()->warning(
                'User does not have the rights to update SAML configuration.',
                ['user_id' => $contact->getId()]
            );

            return $this->view(null, Response::HTTP_FORBIDDEN);
        }

        try {
            $this->validateDataSent($request, __DIR__ . '/UpdateSAMLConfigurationSchema.json');
            $updateRequest = $this->createUpdateSAMLConfigurationRequest($request);
        } catch (\InvalidArgumentException $e) {
            $presenter->setResponseStatus(
                new InvalidArgumentResponse($e->getMessage())
            );

            return $presenter->show();
        } catch (\JsonException $e) {
            ExceptionLogger::create()->log($e);
            $presenter->setResponseStatus(
                new ErrorResponse('Invalid JSON body when trying to update SAML configuration.')
            );

            return $presenter->show();
        }

        $useCase($presenter, $updateRequest);

        return $presenter->show();
    }

    /**
     * @throws \InvalidArgumentException|\JsonException
     */
    private function createUpdateSAMLConfigurationRequest(Request $request): UpdateSAMLConfigurationRequest
    {
        $json = (string) $request->getContent();
        $requestData = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new UpdateSAMLConfigurationRequest(
            isActive: $requestData['is_active'],
            isForced: $requestData['is_forced'],
            remoteLoginUrl: $requestData['remote_login_url'],
            entityIdUrl: $requestData['entity_id_url'],
            publicCertificate: $requestData['certificate'] ?? null,
            userIdAttribute: $requestData['user_id_attribute'],
            requestedAuthnContext: $requestData['requested_authn_context'] ?? false,
            requestedAuthnContextComparison: $requestData['requested_authn_context_comparison'],
            logoutFrom: $requestData['logout_from'],
            logoutFromUrl: $requestData['logout_from_url'] ?? null,
            isAutoImportEnabled: $requestData['auto_import'] ?? false,
            contactTemplate: $requestData['contact_template'] ?? null,
            emailBindAttribute: $requestData['email_bind_attribute'] ?? null,
            userNameBindAttribute: $requestData['fullname_bind_attribute'] ?? null,
            rolesMapping: $requestData['roles_mapping'] ?? [],
            authenticationConditions: $requestData['authentication_conditions'] ?? [],
            groupsMapping: $requestData['groups_mapping'] ?? []
        );
    }
}
