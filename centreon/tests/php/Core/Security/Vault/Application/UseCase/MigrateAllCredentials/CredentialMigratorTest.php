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

namespace Tests\Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Option\Application\Repository\WriteOptionRepositoryInterface;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialMigrator;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;

beforeEach(function (): void {
    $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
    $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class);
    $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class);
    $this->writePollerMacroRepository = $this->createMock(WritePollerMacroRepositoryInterface::class);
    $this->writeOptionRepository = $this->createMock(WriteOptionRepositoryInterface::class);
    $this->writeOpenIdConfigurationRepository = $this->createMock(WriteOpenIdConfigurationRepositoryInterface::class);

    $this->credential1 = new CredentialDto();
    $this->credential1->resourceId = 1;
    $this->credential1->type = CredentialTypeEnum::TYPE_HOST;
    $this->credential1->name = '_HOSTSNMPCOMMUNITY';
    $this->credential1->value = 'community';

    $this->credential2 = new CredentialDto();
    $this->credential2->resourceId = 2;
    $this->credential2->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
    $this->credential2->name = '_HOSTSNMPCOMMUNITY';
    $this->credential2->value = 'community';

    $this->credential3 = new CredentialDto();
    $this->credential3->resourceId = 3;
    $this->credential3->type = CredentialTypeEnum::TYPE_SERVICE;
    $this->credential3->name = '_SERVICEMACRO';
    $this->credential3->value = 'macro';

    $this->hosts = [
        new Host(1, 1, 'Host1', '127.0.0.1'),
    ];

    $this->hostTemplates = [
        new HostTemplate(2, 'HostTemplate1', 'HostTemplate1'),
    ];

    $this->hostMacro = new Macro(1,'_MACRO_HOST1','value');
    $this->hostMacro->setIsPassword(true);
    $this->hostMacros = [$this->hostMacro];

    $this->serviceMacro = new Macro(1,'_MACRO_SERVICE1','value');
    $this->serviceMacro->setIsPassword(true);
    $this->serviceMacros = [$this->serviceMacro];

    $this->pollerMacro = new PollerMacro(1, '$POLLERMACRO$', 'value', null, true, true);
    $this->pollerMacros = [$this->pollerMacro];
    $customConfiguration = new CustomConfiguration([
        'is_active' => true,
        'client_id' => 'MyCl1ientId',
        'client_secret' => 'MyCl1ientSuperSecr3tKey',
        'base_url' => 'http://127.0.0.1/auth/openid-connect',
        'auto_import' => false,
        'authorization_endpoint' => '/authorization',
        'token_endpoint' => '/token',
        'introspection_token_endpoint' => '/introspect',
        'userinfo_endpoint' => '/userinfo',
        'contact_template' => new ContactTemplate(1, 'contact_template'),
        'email_bind_attribute' => null,
        'fullname_bind_attribute' => null,
        'endsession_endpoint' => '/logout',
        'connection_scopes' => [],
        'login_claim' => 'preferred_username',
        'authentication_type' => 'client_secret_post',
        'verify_peer' => false,
        'claim_name' => 'groups',
        'roles_mapping' => new ACLConditions(
            false,
            false,
            '',
            new Endpoint(Endpoint::INTROSPECTION, ''),
            []
        ),
        'authentication_conditions' => new AuthenticationConditions(false, '', new Endpoint(), []),
        'groups_mapping' => new GroupsMapping(false, '', new Endpoint(), []),
        'redirect_url' => null,
    ]);
    $this->openIdProviderConfiguration = new Configuration(1,
        type: Provider::OPENID,
        name: Provider::OPENID,
        jsonCustomConfiguration: '{}',
        isActive: true,
        isForced: false
    );
    $this->openIdProviderConfiguration->setCustomConfiguration($customConfiguration);
});

it('tests getIterator method with hosts, hostTemplates and service macros', function (): void {
    $credentials = new \ArrayIterator([$this->credential1, $this->credential2, $this->credential3]);

    $this->writeVaultRepository->method('upsert')->willReturn('vault/path');

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        writeOpenIdConfigurationRepository: $this->writeOpenIdConfigurationRepository,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe('path');
        expect($status->resourceId)->toBeIn([1, 2, 3]);
        expect($status->vaultPath)->toBe('vault/path');
        expect($status->type)->toBeIn([CredentialTypeEnum::TYPE_HOST, CredentialTypeEnum::TYPE_HOST_TEMPLATE, CredentialTypeEnum::TYPE_SERVICE]);
        expect($status->credentialName)->toBeIn(['_HOSTSNMPCOMMUNITY', '_SERVICEMACRO']);
    }
});

it('tests getIterator method with exception', function (): void {
    $credentials = new \ArrayIterator([$this->credential1]);

    $this->writeVaultRepository->method('upsert')->willThrowException(new \Exception('Test exception'));

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        writeOpenIdConfigurationRepository: $this->writeOpenIdConfigurationRepository,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialErrorDto::class);
        expect($status->resourceId)->toBe(1);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_HOST);
        expect($status->credentialName)->toBe('_HOSTSNMPCOMMUNITY');
        expect($status->message)->toBe('Test exception');
    }
});
