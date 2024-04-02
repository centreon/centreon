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

namespace Core\Security\ProviderConfiguration\Infrastructure\OpenId\Api\PartialUpdateOpenIdConfiguration;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Contact\Contact;
use Core\Security\ProviderConfiguration\Application\OpenId\UseCase\PartialUpdateOpenIdConfiguration\{
    PartialUpdateOpenIdConfiguration,
    PartialUpdateOpenIdConfigurationPresenterInterface,
    PartialUpdateOpenIdConfigurationRequest
};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PartialUpdateOpenIdConfigurationController extends AbstractController
{
    /**
     * @param PartialUpdateOpenIdConfiguration $useCase
     * @param Request $request
     * @param PartialUpdateOpenIdConfigurationPresenterInterface $presenter
     *
     * @return object
     */
    public function __invoke(
        PartialUpdateOpenIdConfiguration $useCase,
        Request $request,
        PartialUpdateOpenIdConfigurationPresenterInterface $presenter
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->hasTopologyRole(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_READ_WRITE)) {
            return $this->view(null, Response::HTTP_FORBIDDEN);
        }
        $this->validateDataSent($request, __DIR__ . '/PartialUpdateOpenIdConfigurationSchema.json');

        $updateOpenIdConfigurationRequest = $this->createPartialUpdateOpenIdConfigurationRequest($request);
        $useCase($presenter, $updateOpenIdConfigurationRequest);

        return $presenter->show();
    }

    /**
     * @param Request $request
     *
     * @return PartialUpdateOpenIdConfigurationRequest
     */
    private function createPartialUpdateOpenIdConfigurationRequest(Request $request): PartialUpdateOpenIdConfigurationRequest
    {
        $json = (string) $request->getContent();
        /**
         * @var array{
         *     is_active?:bool,
         *     is_forced?:bool,
         *     base_url?:string|null,
         *     authorization_endpoint?:string|null,
         *     token_endpoint?:string|null,
         *     introspection_token_endpoint?:string|null,
         *     userinfo_endpoint?:string|null,
         *     endsession_endpoint?:string|null,
         *     connection_scopes?:string[],
         *     login_claim?:string|null,
         *     client_id?:string|null,
         *     client_secret?:string|null,
         *     authentication_type?:string|null,
         *     verify_peer?:bool,
         *     auto_import?:bool,
         *     contact_template?:array{id:int,name:string}|null,
         *     email_bind_attribute?:string|null,
         *     fullname_bind_attribute?:string|null,
         *     redirect_url?:string|null,
         *     authentication_conditions?:array{
         *         is_enabled?:bool,
         *         attribute_path?:string,
         *         authorized_values?:string[],
         *         trusted_client_addresses?:string[],
         *         blacklist_client_addresses?:string[],
         *         endpoint?:array{type:string,custom_endpoint:string|null}
         *     },
         *     roles_mapping?:array{
         *         is_enabled?:bool,
         *         apply_only_first_role?:bool,
         *         attribute_path?:string,
         *         endpoint?:array{type:string,custom_endpoint:string|null},
         *         relations?:array<array{claim_value:string,access_group_id:int,priority:int}>
         *     },
         *     groups_mapping?:array{
         *         is_enabled?:bool,
         *         attribute_path?:string,
         *         endpoint?:array{type:string,custom_endpoint:string|null},
         *         relations?:array<array{group_value:string,contact_group_id:int}>
         *     }
         * } $requestData
         */
        $requestData = json_decode($json, true);
        $updateOpenIdConfigurationRequest = new PartialUpdateOpenIdConfigurationRequest();

        if (array_key_exists('is_active', $requestData)) {
            $updateOpenIdConfigurationRequest->isActive = $requestData['is_active'];
        }
        if (array_key_exists('is_forced', $requestData)) {
            $updateOpenIdConfigurationRequest->isForced = $requestData['is_forced'];
        }
        if (array_key_exists('base_url', $requestData)) {
            $updateOpenIdConfigurationRequest->baseUrl = $requestData['base_url'];
        }
        if (array_key_exists('authorization_endpoint', $requestData)) {
            $updateOpenIdConfigurationRequest->authorizationEndpoint = $requestData['authorization_endpoint'];
        }
        if (array_key_exists('token_endpoint', $requestData)) {
            $updateOpenIdConfigurationRequest->tokenEndpoint = $requestData['token_endpoint'];
        }
        if (array_key_exists('introspection_token_endpoint', $requestData)) {
            $updateOpenIdConfigurationRequest->introspectionTokenEndpoint = $requestData['introspection_token_endpoint'];
        }
        if (array_key_exists('userinfo_endpoint', $requestData)) {
            $updateOpenIdConfigurationRequest->userInformationEndpoint = $requestData['userinfo_endpoint'];
        }
        if (array_key_exists('endsession_endpoint', $requestData)) {
            $updateOpenIdConfigurationRequest->endSessionEndpoint = $requestData['endsession_endpoint'];
        }
        if (array_key_exists('connection_scopes', $requestData)) {
            $updateOpenIdConfigurationRequest->connectionScopes = $requestData['connection_scopes'];
        }
        if (array_key_exists('login_claim', $requestData)) {
            $updateOpenIdConfigurationRequest->loginClaim = $requestData['login_claim'];
        }
        if (array_key_exists('client_id', $requestData)) {
            $updateOpenIdConfigurationRequest->clientId = $requestData['client_id'];
        }
        if (array_key_exists('client_secret', $requestData)) {
            $updateOpenIdConfigurationRequest->clientSecret = $requestData['client_secret'];
        }
        if (array_key_exists('authentication_type', $requestData)) {
            $updateOpenIdConfigurationRequest->authenticationType = $requestData['authentication_type'];
        }
        if (array_key_exists('verify_peer', $requestData)) {
            $updateOpenIdConfigurationRequest->verifyPeer = $requestData['verify_peer'];
        }
        if (array_key_exists('auto_import', $requestData)) {
            $updateOpenIdConfigurationRequest->isAutoImportEnabled = $requestData['auto_import'];
        }
        if (array_key_exists('contact_template', $requestData)) {
            $updateOpenIdConfigurationRequest->contactTemplate = $requestData['contact_template'];
        }
        if (array_key_exists('email_bind_attribute', $requestData)) {
            $updateOpenIdConfigurationRequest->emailBindAttribute = $requestData['email_bind_attribute'];
        }
        if (array_key_exists('fullname_bind_attribute', $requestData)) {
            $updateOpenIdConfigurationRequest->userNameBindAttribute = $requestData['fullname_bind_attribute'];
        }
        if (array_key_exists('redirect_url', $requestData)) {
            $updateOpenIdConfigurationRequest->redirectUrl = $requestData['redirect_url'];
        }

        if (array_key_exists('authentication_conditions', $requestData))  {
            if (array_key_exists('is_enabled', $requestData['authentication_conditions'])) {
                $updateOpenIdConfigurationRequest->authenticationConditions['is_enabled']
                    = $requestData['authentication_conditions']['is_enabled'];
            }
            if (array_key_exists('attribute_path', $requestData['authentication_conditions'])) {
                $updateOpenIdConfigurationRequest->authenticationConditions['attribute_path']
                    = $requestData['authentication_conditions']['attribute_path'];
            }
            if (array_key_exists('authorized_values', $requestData['authentication_conditions'])) {
                $updateOpenIdConfigurationRequest->authenticationConditions['authorized_values']
                    = $requestData['authentication_conditions']['authorized_values'];
            }
            if (array_key_exists('trusted_client_addresses', $requestData['authentication_conditions'])) {
                $updateOpenIdConfigurationRequest->authenticationConditions['trusted_client_addresses']
                    = $requestData['authentication_conditions']['trusted_client_addresses'];
            }
            if (array_key_exists('blacklist_client_addresses', $requestData['authentication_conditions'])) {
                $updateOpenIdConfigurationRequest->authenticationConditions['blacklist_client_addresses']
                    = $requestData['authentication_conditions']['blacklist_client_addresses'];
            }
            if (array_key_exists('endpoint', $requestData['authentication_conditions'])) {
                $updateOpenIdConfigurationRequest->authenticationConditions['endpoint']
                    = $requestData['authentication_conditions']['endpoint'];
            }
        }

        if (array_key_exists('roles_mapping', $requestData)) {
            if (array_key_exists('is_enabled', $requestData['roles_mapping'])) {
                $updateOpenIdConfigurationRequest->rolesMapping['is_enabled']
                    = $requestData['roles_mapping']['is_enabled'];
            }
            if (array_key_exists('apply_only_first_role', $requestData['roles_mapping'])) {
                $updateOpenIdConfigurationRequest->rolesMapping['apply_only_first_role']
                    = $requestData['roles_mapping']['apply_only_first_role'];
            }
            if (array_key_exists('attribute_path', $requestData['roles_mapping'])) {
                $updateOpenIdConfigurationRequest->rolesMapping['attribute_path']
                    = $requestData['roles_mapping']['attribute_path'];
            }
            if (array_key_exists('endpoint', $requestData['roles_mapping'])) {
                $updateOpenIdConfigurationRequest->rolesMapping['endpoint']
                    = $requestData['roles_mapping']['endpoint'];
            }
            if (array_key_exists('relations', $requestData['roles_mapping'])) {
                $updateOpenIdConfigurationRequest->rolesMapping['relations']
                    = $requestData['roles_mapping']['relations'];
            }
        }

        if (array_key_exists('groups_mapping', $requestData))  {
            if (array_key_exists('is_enabled', $requestData['groups_mapping'])) {
                $updateOpenIdConfigurationRequest->groupsMapping['is_enabled']
                    = $requestData['groups_mapping']['is_enabled'];
            }
            if (array_key_exists('attribute_path', $requestData['groups_mapping'])) {
                $updateOpenIdConfigurationRequest->groupsMapping['attribute_path']
                    = $requestData['groups_mapping']['attribute_path'];
            }
            if (array_key_exists('endpoint', $requestData['groups_mapping'])) {
                $updateOpenIdConfigurationRequest->groupsMapping['endpoint']
                    = $requestData['groups_mapping']['endpoint'];
            }
            if (array_key_exists('relations', $requestData['groups_mapping'])) {
                $updateOpenIdConfigurationRequest->groupsMapping['relations']
                    = $requestData['groups_mapping']['relations'];
            }
        }

        return $updateOpenIdConfigurationRequest;
    }
}
