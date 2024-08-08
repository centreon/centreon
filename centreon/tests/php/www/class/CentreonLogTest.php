<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

function testSerializeContext(array $customContext, Throwable $exception = null)
{
    $context = [
        'context' => [
            'default' => [
                'back_trace' => [
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'class' => null,
                    'function' => null
                ],
                'request_infos' => [
                    'url' => null,
                    'http_method' => null,
                    'server' => null,
                    'referrer' => null
                ]
            ],
            'exception' => null,
            'custom' => [
                'custom_value1' => 'foo',
                'custom_value2' => 'bar'
            ]
        ]
    ];
    return json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

beforeAll(function () {
    $pathToLogTest = __DIR__ . '/log';
    $loggerTest = new CentreonLog([99 => 'custom.log']);
    $loggerTest->setPathLogFile($pathToLogTest);
    $loggerTest
        ->pushLogFileHandler(CentreonLog::TYPE_LOGIN, 'error-login.log')
        ->pushLogFileHandler(CentreonLog::TYPE_SQL, 'error-sql.log')
        ->pushLogFileHandler(CentreonLog::TYPE_LDAP, 'error-ldap.log')
        ->pushLogFileHandler(CentreonLog::TYPE_UPGRADE, 'error-upgrade.log')
        ->pushLogFileHandler(CentreonLog::TYPE_PLUGIN_PACK_MANAGER, 'error-plugin.log');
});

it('test log file handler is correct', function () {
    expect($this->loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => 'error-login.log',
            CentreonLog::TYPE_SQL => 'error-sql.log',
            CentreonLog::TYPE_LDAP => 'error-ldap.log',
            CentreonLog::TYPE_UPGRADE => 'error-upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => 'error-plugin.log',
            99 => 'custom.log',
        ]
    );
});

it('test writing the log to the login file', function () {
    $this->loggerTest->log(CentreonLog::TYPE_LOGIN, CentreonLog::LEVEL_ERROR, 'login_message');
    expect(file_exists($this->pathToLogTest . 'error-login.log'))->toBeTrue();
    $contentLog = file_get_contents($this->pathToLogTest . 'error-login.log');
    expect($contentLog)->toBeString()->toEqual();
});

it('test writing the log to the sql file', function () {
    $this->loggerTest->log(CentreonLog::TYPE_SQL, CentreonLog::LEVEL_ERROR, 'sql_message');
});

it('test writing the log to the ldap file', function () {
    $this->loggerTest->log(CentreonLog::TYPE_LDAP, CentreonLog::LEVEL_ERROR, 'ldap_message');
});

it('test writing the log to the upgrade file', function () {
    $this->loggerTest->log(CentreonLog::TYPE_UPGRADE, CentreonLog::LEVEL_ERROR, 'upgrade_message');
});

it('test writing the log to the plugin pack manager file', function () {
    $this->loggerTest->log(
        CentreonLog::TYPE_PLUGIN_PACK_MANAGER,
        CentreonLog::LEVEL_ERROR,
        'plugin_pack_manager_message'
    );
});

it('test writing the log to the custom log file', function () {
    $this->loggerTest->log(99, CentreonLog::LEVEL_ERROR, 'custom_message');
});

it('test writing logs with all levels', function () {
    $this->loggerTest->debug(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->notice(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->info(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->warning(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->error(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->critical(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->alert(CentreonLog::TYPE_LOGIN, 'login_message');
    $this->loggerTest->emergency(CentreonLog::TYPE_LOGIN, 'login_message');
});
