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
 *  For more information : contact@centreon.com
 */

declare(strict_types=1);

namespace Tests\Core\Security\ProviderConfiguration\Domain\OpenId\Model;

use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\{
    ACLConditions,
    AuthenticationConditions,
    CustomConfiguration,
    Endpoint,
    GroupsMapping
};

it(
    'should sanitize URL/endpoint values when they contain additional slashes and/or spaces in the beginning and end',
    function () {
        $json = [
            'is_active' => true,
            'is_forced' => false,
            'base_url' => '   https://localhost:8080',
            'authorization_endpoint' => '/authorize/ ',
            'token_endpoint' => '/token  ',
            'introspection_token_endpoint' => '// introspection/',
            'userinfo_endpoint' => '//userinfo',
            'endsession_endpoint' => '/ logout',
            'connection_scopes' => ['openid', 'offline_access'],
            'login_claim' => 'given_name',
            'client_id' => 'user2',
            'client_secret' => 'Centreon!2021',
            'authentication_type' => 'client_secret_post',
            'verify_peer' => false,
            'auto_import' => true,
            'contact_template' => (new ContactTemplate(19, 'contact_template')),
            'email_bind_attribute' => 'email',
            'fullname_bind_attribute' => 'given_name',
            'authentication_conditions' => (
                new AuthenticationConditions(
                    true,
                    'users.roles.info.status',
                    (new Endpoint('custom_endpoint', '  /my/custom/endpoint')),
                    ['status2']
                )
            ),
            'roles_mapping' => (
                new ACLConditions(
                    false,
                    false,
                    'users.roles.info.status',
                    (new Endpoint('custom_endpoint', '///my/custom/endpoint'))
                )
            ),
            'groups_mapping' => (
                new GroupsMapping(
                    false,
                    'users.roles.info.status',
                    (new Endpoint('custom_endpoint', '/my/custom/endpoint//')),
                    []
                )
            )
        ];

        $customConfiguration = new CustomConfiguration($json);

        expect($customConfiguration->getBaseUrl())->toBe('https://localhost:8080');
        expect($customConfiguration->getAuthorizationEndpoint())->toBe('/authorize');
        expect($customConfiguration->getTokenEndpoint())->toBe('/token');
        expect($customConfiguration->getIntrospectionTokenEndpoint())->toBe('/introspection');
        expect($customConfiguration->getUserInformationEndpoint())->toBe('/userinfo');
        expect($customConfiguration->getEndSessionEndpoint())->toBe('/logout');
        expect($customConfiguration->getAuthenticationConditions()->getEndpoint()->getUrl())
            ->toBe('/my/custom/endpoint');
        expect($customConfiguration->getACLConditions()->getEndpoint()->getUrl())->toBe('/my/custom/endpoint');
        expect($customConfiguration->getGroupsMapping()->getEndpoint()->getUrl())->toBe('/my/custom/endpoint');
    }
);