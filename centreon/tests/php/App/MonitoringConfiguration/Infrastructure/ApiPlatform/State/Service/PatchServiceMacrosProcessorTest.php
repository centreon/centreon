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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\VaultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Shared\Double\FakeVault;
use Webmozart\Assert\Assert;

/**
 * The own macros of a service through the partial update: three keys of which one only can be sent, and the vault
 * entry the password macros share.
 */
final class PatchServiceMacrosProcessorTest extends PatchServiceProcessorTestCase
{
    private const SERVICE_PWD = 'secret::vault::monitoring/services/svc-uuid::_SERVICEPWD';

    private int $serviceId;

    private int $portId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()), ['command_command_id' => $this->insertCommand()]);
        $this->portId = $this->insertMacro($this->serviceId, 'PORT', '80');
        $this->userId = $this->insertMacro($this->serviceId, 'USER', 'admin');
        $this->login();
    }

    public function testTheMacrosAreReplacedWrittenAndRemoved(): void
    {
        $this->patch($this->serviceId, ['check_options' => ['macros_to_upsert' => [
            ['id' => $this->portId, 'name' => 'port', 'value' => '443', 'is_password' => false],
            ['name' => 'path', 'value' => '/', 'is_password' => false],
        ]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_SERVICEPATH$' => '/', '$_SERVICEPORT$' => '443', '$_SERVICEUSER$' => 'admin'], $this->macros());
        self::assertSame($this->portId, $this->macroId('$_SERVICEPORT$'));

        $this->patch($this->serviceId, ['check_options' => ['macros_to_remove' => [$this->userId]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_SERVICEPATH$' => '/', '$_SERVICEPORT$' => '443'], $this->macros());

        $this->patch($this->serviceId, ['check_options' => ['macros' => [['id' => $this->portId, 'name' => 'port', 'value' => '8080', 'is_password' => false]]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_SERVICEPORT$' => '8080'], $this->macros());

        self::assertSame(['mc', 'mc', 'mc'], array_column($this->activityLogs($this->serviceId), 'action_type'));
    }

    public function testTheMacrosAreLeftAloneByAnUpdateOfAnotherKey(): void
    {
        $this->patch($this->serviceId, ['check_options' => ['args' => ['80']]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['$_SERVICEPORT$' => '80', '$_SERVICEUSER$' => 'admin'], $this->macros());
    }

    public function testItAcceptsAMacroValueOfTheLengthLimit(): void
    {
        $value = str_repeat('v', 4096);

        $this->patch($this->serviceId, ['check_options' => ['macros_to_upsert' => [['name' => 'long', 'value' => $value]]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame($value, $this->macros()['$_SERVICELONG$'] ?? null);
    }

    /**
     * @param array<string, mixed> $checkOptions
     * @param non-empty-string $message
     */
    #[DataProvider('refusedMacrosProvider')]
    public function testTheMacroRulesAreEnforced(array $checkOptions, string $message): void
    {
        $this->connection->insert('nagios_macro', ['macro_name' => '$_SERVICERESERVEDTEST$']);

        $response = $this->patch($this->serviceId, ['check_options' => $checkOptions]);

        self::assertResponseStatusCodeSame(422);
        self::assertMessageStartsWith($message, $response);
        self::assertSame(['$_SERVICEPORT$' => '80', '$_SERVICEUSER$' => 'admin'], $this->macros());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, non-empty-string}>
     */
    public static function refusedMacrosProvider(): iterable
    {
        yield 'two macros of one name' => [
            ['macros_to_upsert' => [['name' => 'a', 'value' => '1'], ['name' => 'A', 'value' => '2']]],
            '[check_options.macros_to_upsert[1].name] Two macros cannot have the same name.',
        ];

        yield 'a new macro under a name the service already has' => [
            ['macros_to_upsert' => [['name' => 'user', 'value' => 'root']]],
            '[check_options] A service cannot have two macros with the same name.',
        ];

        yield 'a reserved name' => [['macros' => [['name' => 'reservedtest', 'value' => '1']]], '[check_options.macros[0].name]'];

        yield 'a vault reference as a value' => [['macros_to_upsert' => [['name' => 'pwd', 'value' => self::SERVICE_PWD, 'is_password' => true]]], '[check_options.macros_to_upsert[0].value] A macro value cannot be a vault reference.'];

        yield 'no value for a plain macro' => [['macros_to_upsert' => [['name' => 'port', 'value' => null]]], '[check_options.macros_to_upsert[0].value]'];

        yield 'a removal of an id the service does not own' => [['macros_to_remove' => [999999999]], '[check_options] One or more macros do not exist on this service.'];

        yield 'a value too long' => [['macros_to_upsert' => [['name' => 'long', 'value' => str_repeat('v', 4097)]]], '[check_options.macros_to_upsert[0].value]'];

        yield 'a list of macros sent as null' => [['macros' => null], '[check_options.macros]'];

        yield 'a new password without value' => [['macros_to_upsert' => [['name' => 'pwd', 'value' => null, 'is_password' => true]]], '[check_options'];
    }

    public function testAPasswordMacroCannotBeUnmasked(): void
    {
        $passwordId = $this->insertMacro($this->serviceId, 'PWD', self::SERVICE_PWD, isPassword: true);
        $before = $this->macros();

        $response = $this->patch($this->serviceId, ['check_options' => ['macros_to_upsert' => [['id' => $passwordId, 'name' => 'pwd', 'value' => 'x', 'is_password' => false]]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertMessageStartsWith('[check_options] These macros are passwords and must stay so', $response);
        self::assertSame($before, $this->macros());
    }

    /**
     * An unreachable vault is an upstream failure: nothing is saved, nothing is logged.
     */
    public function testAVaultFailureAnswers502AndSavesNothing(): void
    {
        $vault = $this->useFakeVault();
        $vault->writeThrows = true;

        $this->patch($this->serviceId, [
            'name' => 'pong',
            'check_options' => ['macros_to_upsert' => [['name' => 'pwd', 'value' => 's3cr3t', 'is_password' => true]]],
        ]);

        self::assertResponseStatusCodeSame(502);
        self::assertSame('ping', $this->column('service_description', $this->serviceId));
        self::assertSame(['$_SERVICEPORT$' => '80', '$_SERVICEUSER$' => 'admin'], $this->macros());
        self::assertSame([], $this->activityLogs($this->serviceId));
    }

    public function testAPasswordIsWrittenToTheServiceEntry(): void
    {
        $vault = $this->useFakeVault();

        $this->patch($this->serviceId, ['check_options' => ['macros_to_upsert' => [['name' => 'pwd', 'value' => 's3cr3t', 'is_password' => true]]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([['customPath' => 'monitoring/services', 'secrets' => ['_SERVICEPWD' => 's3cr3t'], 'uuid' => null, 'deletes' => []]], $vault->writeManyCalls);
        self::assertSame('secret::vault::monitoring/services/new-uuid::_SERVICEPWD', $this->macros()['$_SERVICEPWD$'] ?? null);
    }

    /**
     * The last password removed: its key is deleted before the save, and the entry, left without any secret, once
     * the save is committed.
     */
    public function testRemovingTheLastPasswordReleasesTheServiceEntry(): void
    {
        $passwordId = $this->insertMacro($this->serviceId, 'PWD', self::SERVICE_PWD, isPassword: true);
        $vault = $this->useFakeVault();

        $this->patch($this->serviceId, ['check_options' => ['macros_to_remove' => [$passwordId]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['_SERVICEPWD'], $vault->writeManyCalls[0]['deletes'] ?? null);
        self::assertSame([['customPath' => 'monitoring/services', 'uuid' => 'svc-uuid']], $vault->deleteCalls);
        self::assertArrayNotHasKey('$_SERVICEPWD$', $this->macros());
    }

    /**
     * A password sent back without value keeps its stored secret: nothing to write.
     */
    public function testAPasswordSentWithoutValueChangesNothing(): void
    {
        $passwordId = $this->insertMacro($this->serviceId, 'PWD', self::SERVICE_PWD, isPassword: true);
        $vault = $this->useFakeVault();

        $this->patch($this->serviceId, ['check_options' => ['macros_to_upsert' => [['id' => $passwordId, 'name' => 'pwd', 'value' => null, 'is_password' => true]]]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $vault->writeManyCalls);
        self::assertSame([], $this->activityLogs($this->serviceId));
    }

    /**
     * Swapped in before the one request of the test: the kernel is rebooted between two requests.
     */
    private function useFakeVault(): FakeVault
    {
        $vault = new FakeVault();
        self::getContainer()->set(VaultInterface::class, $vault);
        self::getContainer()->set(VaultCredentialWriter::class, new VaultCredentialWriter($vault));

        return $vault;
    }

    /**
     * @return array<string, string> the own macros of the service, value by name
     */
    private function macros(): array
    {
        $macros = $this->connection->fetchAllKeyValue(
            'SELECT svc_macro_name, svc_macro_value FROM on_demand_macro_service WHERE svc_svc_id = ? ORDER BY svc_macro_name',
            [$this->serviceId],
        );
        $byName = [];
        foreach ($macros as $name => $value) {
            Assert::string($value);
            $byName[(string) $name] = $value;
        }

        return $byName;
    }

    private function macroId(string $name): int
    {
        $id = $this->connection->fetchOne('SELECT svc_macro_id FROM on_demand_macro_service WHERE svc_svc_id = ? AND svc_macro_name = ?', [$this->serviceId, $name]);
        self::assertIsNumeric($id);

        return (int) $id;
    }
}
