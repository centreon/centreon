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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\CreateHostTemplateCommand;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateDataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateMacroInput;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

/**
 * `LoggingMiddleware` logs the command payload on every dispatch, and the secrets travel on it in
 * plaintext: the vaulting only happens later, in the handler. Nothing in the keyword denylist
 * matches these keys, so only `#[Sensitive]` keeps them out of the logs.
 */
final class CreateHostTemplateCommandLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();
    }

    public function testTheSecretsOfTheCommandNeverReachTheLogs(): void
    {
        $command = new CreateHostTemplateCommand(
            name: new HostTemplateName('tpl'),
            alias: new HostAlias('alias'),
            actor: TestActingUsers::unrestricted(1),
            snmpCommunity: 'top-secret-community',
            checkCommandArgs: ['--password=top-secret-arg'],
            macroChanges: new Collection([
                new HostMacroChange(new HostMacroName('token'), 'top-secret-macro', isPassword: true),
            ], HostMacroChange::class),
            dataProcessing: new DataProcessing(eventHandlerArgs: ['--password=top-secret-handler']),
        );

        $sanitised = $this->sanitise($command);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised['snmp_community']);
        self::assertSame('***', $sanitised['check_command_args']);
        self::assertSame('***', $sanitised['macro_changes']);
        self::assertIsArray($sanitised['data_processing']);
        self::assertSame('***', $sanitised['data_processing']['event_handler_args']);
        self::assertStringNotContainsString('top-secret', json_encode($sanitised, flags: JSON_THROW_ON_ERROR));
        self::assertSame(['user_id' => '1', 'unrestricted_resource_access' => true], $sanitised['actor'], 'The acting user must stay readable in the logs.');
        self::assertArrayHasKey('name', $sanitised);
    }

    #[DataProvider('provideInputsCarryingASecret')]
    public function testTheSecretOfAnInputNeverReachesTheLogs(object $input, string $key): void
    {
        $sanitised = $this->sanitise($input);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised[$key]);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function provideInputsCarryingASecret(): iterable
    {
        yield 'the snmp community' => [new CreateHostTemplateInput(snmpCommunity: 'top-secret'), 'snmp_community'];

        yield 'the check arguments' => [new CreateHostTemplateCheckOptionsInput(args: ['top-secret']), 'args'];

        yield 'the event handler arguments' => [new CreateHostTemplateDataProcessingInput(eventHandlerArgs: ['top-secret']), 'event_handler_args'];

        yield 'a macro value' => [new HostTemplateMacroInput(name: 'token', value: 'top-secret', isPassword: true), 'value'];
    }

    private function sanitise(object $payload): mixed
    {
        $normalised = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter())->normalize($payload);

        return new PayloadSanitizer()->sanitize($normalised, $payload::class);
    }
}
