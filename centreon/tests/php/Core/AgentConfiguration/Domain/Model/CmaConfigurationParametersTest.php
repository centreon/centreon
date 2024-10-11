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
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;

beforeEach(function (): void {
    $this->parameters = [
        'is_reverse' => true,
        'otel_public_certificate' => 'otel_certif_filename',
        'otel_ca_certificate' => 'ca_certif_filename',
        'otel_private_key' => 'otel_key_filename',
        'hosts' => [
            [
                'address' => '0.0.0.0',
                'port' => 442,
                'poller_ca_certificate' => 'poller_certif',
                'poller_ca_name' => 'ca_name'
            ]
        ]
    ];
});

foreach (
    [
        'port',
    ] as $field
) {
    it(
        "should throw an exception when the hosts[].{$field} is not valid",
        function () use ($field) : void {
            $this->parameters['hosts'][0][$field] = 9999999999;
            new CmaConfigurationParameters($this->parameters);
        }
    )->throws(
        AssertionException::range(
            9999999999,
            0,
            65535,
            "configuration.hosts[].{$field}"
        )->getMessage()
    );
}

foreach (
    [
        'otel_public_certificate',
        'otel_ca_certificate',
        'otel_private_key'
    ] as $field
) {
    it(
        "should throw an exception when a {$field} is too short",
        function () use ($field) : void {
            $this->parameters[$field] = '';

            new CmaConfigurationParameters($this->parameters);
        }
    )->throws(
        AssertionException::notEmptyString("configuration.{$field}")->getMessage()
    );
}

foreach (
    [
        'otel_public_certificate',
        'otel_ca_certificate',
        'otel_private_key'
    ] as $field
) {
    $tooLong = str_repeat('a', CmaConfigurationParameters::MAX_LENGTH + 1);
    it(
        "should throw an exception when a {$field} is too long",
        function () use ($field, $tooLong) : void {
            $this->parameters[$field] = $tooLong;

            new CmaConfigurationParameters($this->parameters);
        }
    )->throws(
        AssertionException::maxLength(
            $tooLong,
            CmaConfigurationParameters::MAX_LENGTH + 1,
            CmaConfigurationParameters::MAX_LENGTH,
            "configuration.{$field}"
        )->getMessage()
    );
}
