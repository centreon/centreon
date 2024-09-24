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

namespace Core\AdditionalConnectorConfiguration\Application\Validation;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Security\Interfaces\EncryptionInterface;

beforeEach(function (): void {
    $this->parameters = [
        "otel_server_address" => "0.0.0.0",
        "otel_server_port" => 456,
        "otel_public_certificate" => "otel_certif_filename",
        "otel_ca_certificate" => "ca_certif_filename",
        "otel_private_key" => "otel_key_filename",
        "conf_server_port" => 489,
        "conf_certificate" => "conf_certif_filename",
        "conf_private_key" => "conf_key_filename"
    ];
});

it(
    "should throw an exception when the otel_server_address is not valid",
    function () : void {
        $this->parameters['otel_server_address'] = 'someTestText';
        new TelegrafConfigurationParameters($this->parameters);
    }
)->throws(
    AssertionException::ipAddressNotValid(
        'someTestText',
        "configuration.otel_server_address"
    )->getMessage()
);

foreach (
    [
        'otel_server_port',
        'conf_server_port',
    ] as $field
) {
    it(
        "should throw an exception when the {$field} is not valid",
        function () use ($field) : void {
            $this->parameters[$field] = 9999999999;
            new TelegrafConfigurationParameters($this->parameters);
        }
    )->throws(
        AssertionException::range(
            9999999999,
            0,
            65535,
            "configuration.{$field}"
        )->getMessage()
    );
}

foreach (
    [
        'otel_public_certificate',
        'otel_ca_certificate',
        'otel_private_key',
        'conf_certificate',
        'conf_private_key',
    ] as $field
) {
    it(
        "should throw an exception when a {$field} is too short",
        function () use ($field) : void {
            $this->parameters[$field] = '';

            new TelegrafConfigurationParameters($this->parameters);
        }
    )->throws(
        AssertionException::notEmptyString("configuration.{$field}")->getMessage()
    );
}
