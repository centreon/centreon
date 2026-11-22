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

namespace Tests\App\AiConfiguration\Infrastructure\Security;

use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Domain\Security\AiProviderPermissionEnum;
use App\AiConfiguration\Infrastructure\Security\AiProviderActionVoter;
use App\AiConfiguration\Infrastructure\Security\AiProviderPermissionVoter;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * The action voter delegates to the permission voter through a real Security.
 */
final class AiProviderActionVoterTest extends TestCase
{
    public function testAbstainsOnAnUnrelatedAttribute(): void
    {
        $token = $this->tokenWith([AiProviderPermissionEnum::CanReadAndWrite]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($token, 'some_other_attribute'));
    }

    /**
     * @param list<AiProviderPermissionEnum> $permissions
     */
    #[DataProvider('provideDecisions')]
    public function testDecision(array $permissions, AiProviderActionEnum $action, int $expected): void
    {
        self::assertSame($expected, $this->vote($this->tokenWith($permissions), $action->value));
    }

    /**
     * @return iterable<string, array{list<AiProviderPermissionEnum>, AiProviderActionEnum, int}>
     */
    public static function provideDecisions(): iterable
    {
        $read = [AiProviderPermissionEnum::CanRead];
        $readWrite = [AiProviderPermissionEnum::CanReadAndWrite];

        yield 'read-only user reads' => [$read, AiProviderActionEnum::Read, VoterInterface::ACCESS_GRANTED];

        yield 'read-only user can not create' => [$read, AiProviderActionEnum::Create, VoterInterface::ACCESS_DENIED];

        yield 'read-only user can not update' => [$read, AiProviderActionEnum::Update, VoterInterface::ACCESS_DENIED];

        yield 'read-only user can not delete' => [$read, AiProviderActionEnum::Delete, VoterInterface::ACCESS_DENIED];

        yield 'read-write user reads' => [$readWrite, AiProviderActionEnum::Read, VoterInterface::ACCESS_GRANTED];

        yield 'read-write user creates' => [$readWrite, AiProviderActionEnum::Create, VoterInterface::ACCESS_GRANTED];

        yield 'read-write user updates' => [$readWrite, AiProviderActionEnum::Update, VoterInterface::ACCESS_GRANTED];

        yield 'read-write user deletes' => [$readWrite, AiProviderActionEnum::Delete, VoterInterface::ACCESS_GRANTED];

        yield 'user without permission can not read' => [[], AiProviderActionEnum::Read, VoterInterface::ACCESS_DENIED];

        yield 'user without permission can not create' => [[], AiProviderActionEnum::Create, VoterInterface::ACCESS_DENIED];
    }

    public function testDeniesWhenNotAuthenticatedAsACredentialUser(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote(new NullToken(), AiProviderActionEnum::Read->value)
        );
    }

    private function vote(TokenInterface $token, string $attribute): int
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set(
            'security.authorization_checker',
            new AuthorizationChecker($tokenStorage, new AccessDecisionManager([new AiProviderPermissionVoter()]))
        );

        return (new AiProviderActionVoter(new Security($container)))->vote($token, null, [$attribute]);
    }

    /**
     * @param list<AiProviderPermissionEnum> $permissions
     */
    private function tokenWith(array $permissions): UsernamePasswordToken
    {
        $credential = new Credential(
            identifier: new CredentialIdentifier('editor'),
            userId: new UserId(1),
            active: true,
        );
        foreach ($permissions as $permission) {
            $credential->grantPermission(new Permission($permission->value));
        }

        return new UsernamePasswordToken(new CredentialUser($credential), 'main');
    }
}
