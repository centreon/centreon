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

namespace Tests\Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration;

use Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration\UpdateSAMLConfigurationRequest;

it('should throw an exception when a request property is invalid (active but no certificate)', function (): void {
    new UpdateSAMLConfigurationRequest(
        isActive: true,
        publicCertificate: null,
    );
})->throws(\InvalidArgumentException::class);

it('should throw an exception when a request property is invalid (active but no entityIdUrl)', function (): void {
    new UpdateSAMLConfigurationRequest(
        isActive: true,
        entityIdUrl: '',
    );
})->throws(\InvalidArgumentException::class);

it('should throw an exception when a request property is invalid (active but no remoteLoginUrl)', function (): void {
    new UpdateSAMLConfigurationRequest(
        isActive: true,
        remoteLoginUrl: '',
        publicCertificate: 'my-certificate',
    );
})->throws(\InvalidArgumentException::class);

it('should throw an exception when a request property is invalid (active but no userIdAttribute)', function (): void {
    new UpdateSAMLConfigurationRequest(
        isActive: true,
        remoteLoginUrl: 'http://remote.login.url',
        publicCertificate: 'my-certificate',
        userIdAttribute: '',
    );
})->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (active with requested_authn_context with invalid value for requested_authn_context_comparison)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            requestedAuthnContext: true,
            requestedAuthnContextComparison: 'invalid-value',
        );
    }
)->throws(\InvalidArgumentException::class);

it('should throw an exception when a request property is invalid (logoutFrom but no logoutFromUrl)', function (): void {
    new UpdateSAMLConfigurationRequest(
        isActive: true,
        remoteLoginUrl: 'http://remote.login.url',
        publicCertificate: 'my-certificate',
        userIdAttribute: 'email',
        logoutFrom: true,
        logoutFromUrl: null,
    );
})->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (logoutFrom but logoutFromUrl is empty)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: true,
            logoutFromUrl: '',
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (autoImport but no emailBindAttribute)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: false,
            isAutoImportEnabled: true,
            emailBindAttribute: null,
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (autoImport but emailBindAttribute is empty)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: false,
            isAutoImportEnabled: true,
            emailBindAttribute: '',
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (autoImport but no userNameBindAttribute)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: false,
            isAutoImportEnabled: true,
            emailBindAttribute: 'email',
            userNameBindAttribute: null,
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (autoImport but userNameBindAttribute is empty)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: false,
            isAutoImportEnabled: true,
            emailBindAttribute: 'email',
            userNameBindAttribute: '',
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (autoImport but no contactTemplate)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: false,
            isAutoImportEnabled: true,
            contactTemplate: null,
            emailBindAttribute: 'email',
            userNameBindAttribute: 'fullname',
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should throw an exception when a request property is invalid (autoImport but contactTemplate is empty)',
    function (): void {
        new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            logoutFrom: false,
            isAutoImportEnabled: true,
            contactTemplate: [],
            emailBindAttribute: 'email',
            userNameBindAttribute: 'fullname',
        );
    }
)->throws(\InvalidArgumentException::class);

it(
    'should instance request with success (not active, without forced, without requested_authn_context, without auto import)',
    function (): void {
        $request = new UpdateSAMLConfigurationRequest(
            isActive: false,
        );
        expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
            ->and($request->isActive)->toBeFalse()
            ->and($request->isForced)->toBeFalse()
            ->and($request->remoteLoginUrl)->toBe('')
            ->and($request->publicCertificate)->toBeNull()
            ->and($request->userIdAttribute)->toBe('')
            ->and($request->logoutFrom)->toBeTrue()
            ->and($request->isAutoImportEnabled)->toBeFalse()
            ->and($request->requestedAuthnContext)->toBeFalse()
            ->and($request->requestedAuthnContextComparison->value)->toBe('exact');
    }
);

it('should instance the request with success (not active but forced)', function (): void {
    $request = new UpdateSAMLConfigurationRequest(
        isActive: false,
        isForced: true,
    );
    expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
        ->and($request->isActive)->toBeFalse()
        ->and($request->isForced)->toBeTrue();
});

it('should instance the request with success (not active but has auto import)', function (): void {
    $request = new UpdateSAMLConfigurationRequest(
        isActive: false,
        isAutoImportEnabled: true,
    );
    expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
        ->and($request->isActive)->toBeFalse()
        ->and($request->isAutoImportEnabled)->toBeTrue();
});

it('should instance the request with success (not active but has requested_authn_context)', function (): void {
    $request = new UpdateSAMLConfigurationRequest(
        isActive: false,
        requestedAuthnContext: true,
        requestedAuthnContextComparison: 'minimum',
    );
    expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
        ->and($request->isActive)->toBeFalse()
        ->and($request->requestedAuthnContext)->toBeTrue()
        ->and($request->requestedAuthnContextComparison->value)->toBe('minimum');
});

it(
    'should instance request with success (active without requested_authn_context, without auto import)',
    function (): void {
        $request = new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            entityIdUrl: 'http://entity.id.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            requestedAuthnContext: false,
            logoutFrom: false,
            isAutoImportEnabled: false,
        );
        expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
            ->and($request->isActive)->toBeTrue()
            ->and($request->isForced)->toBeFalse()
            ->and($request->entityIdUrl)->toBe('http://entity.id.url')
            ->and($request->remoteLoginUrl)->toBe('http://remote.login.url')
            ->and($request->publicCertificate)->toBe('my-certificate')
            ->and($request->userIdAttribute)->toBe('email')
            ->and($request->logoutFrom)->toBeFalse()
            ->and($request->isAutoImportEnabled)->toBeFalse()
            ->and($request->requestedAuthnContext)->toBeFalse()
            ->and($request->requestedAuthnContextComparison->value)->toBe('exact');
    }
);

it(
    'should instance request with success (active without requested_authn_context, with auto import)',
    function (): void {
        $request = new UpdateSAMLConfigurationRequest(
            isActive: true,
            remoteLoginUrl: 'http://remote.login.url',
            entityIdUrl: 'http://entity.id.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            requestedAuthnContext: false,
            logoutFrom: false,
            isAutoImportEnabled: true,
            contactTemplate: ['template' => 'value'],
            emailBindAttribute: 'email',
            userNameBindAttribute: 'fullname',
        );
        expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
            ->and($request->isActive)->toBeTrue()
            ->and($request->isForced)->toBeFalse()
            ->and($request->remoteLoginUrl)->toBe('http://remote.login.url')
            ->and($request->remoteLoginUrl)->toBe('http://remote.login.url')
            ->and($request->publicCertificate)->toBe('my-certificate')
            ->and($request->userIdAttribute)->toBe('email')
            ->and($request->logoutFrom)->toBeFalse()
            ->and($request->isAutoImportEnabled)->toBeTrue()
            ->and($request->emailBindAttribute)->toBe('email')
            ->and($request->userNameBindAttribute)->toBe('fullname')
            ->and($request->contactTemplate)->toBe(['template' => 'value'])
            ->and($request->requestedAuthnContext)->toBeFalse()
            ->and($request->requestedAuthnContextComparison->value)->toBe('exact');
    }
);

it(
    'should instance request with success (active with forced, with requested_authn_context, with auto import)',
    function (): void {
        $request = new UpdateSAMLConfigurationRequest(
            isActive: true,
            isForced: true,
            remoteLoginUrl: 'http://remote.login.url',
            entityIdUrl: 'http://entity.id.url',
            publicCertificate: 'my-certificate',
            userIdAttribute: 'email',
            requestedAuthnContext: true,
            requestedAuthnContextComparison: 'minimum',
            logoutFrom: false,
            isAutoImportEnabled: true,
            contactTemplate: ['template' => 'value'],
            emailBindAttribute: 'email',
            userNameBindAttribute: 'fullname',
        );
        expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
            ->and($request->isActive)->toBeTrue()
            ->and($request->isForced)->toBeTrue()
            ->and($request->entityIdUrl)->toBe('http://entity.id.url')
            ->and($request->remoteLoginUrl)->toBe('http://remote.login.url')
            ->and($request->publicCertificate)->toBe('my-certificate')
            ->and($request->userIdAttribute)->toBe('email')
            ->and($request->logoutFrom)->toBeFalse()
            ->and($request->isAutoImportEnabled)->toBeTrue()
            ->and($request->emailBindAttribute)->toBe('email')
            ->and($request->userNameBindAttribute)->toBe('fullname')
            ->and($request->contactTemplate)->toBe(['template' => 'value'])
            ->and($request->requestedAuthnContext)->toBeTrue()
            ->and($request->requestedAuthnContextComparison->value)->toBe('minimum');
    }
);

it('should have an array from request with success', function (): void {
    $request = new UpdateSAMLConfigurationRequest(
        isActive: true,
        isForced: true,
        remoteLoginUrl: 'http://remote.login.url',
        entityIdUrl: 'http://entity.id.url',
        publicCertificate: 'my-certificate',
        userIdAttribute: 'email',
        requestedAuthnContext: true,
        requestedAuthnContextComparison: 'minimum',
        logoutFrom: true,
        logoutFromUrl: 'http://logout.from.url',
        isAutoImportEnabled: true,
        contactTemplate: ['template' => 'value'],
        emailBindAttribute: 'email',
        userNameBindAttribute: 'fullname',
    );
    expect($request)->toBeInstanceOf(UpdateSAMLConfigurationRequest::class)
        ->and($request->toArray())->toBe([
            'entity_id_url' => 'http://entity.id.url',
            'remote_login_url' => 'http://remote.login.url',
            'user_id_attribute' => 'email',
            'requested_authn_context' => true,
            'requested_authn_context_comparison' => 'minimum',
            'certificate' => 'my-certificate',
            'logout_from' => true,
            'logout_from_url' => 'http://logout.from.url',
            'contact_template' => ['template' => 'value'],
            'auto_import' => true,
            'email_bind_attribute' => 'email',
            'fullname_bind_attribute' => 'fullname',
            'authentication_conditions' => [
                'is_enabled' => false,
                'attribute_path' => '',
                'authorized_values' => [],
                'trusted_client_addresses' => [],
                'blacklist_client_addresses' => [],
            ],
            'groups_mapping' => [
                'is_enabled' => false,
                'attribute_path' => '',
                'relations' => [],
            ],
            'roles_mapping' => [
                'is_enabled' => false,
                'apply_only_first_role' => false,
                'attribute_path' => '',
                'relations' => [],
            ],
        ]);
});
