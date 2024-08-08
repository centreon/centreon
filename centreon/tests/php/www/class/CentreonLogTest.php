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

/*
 * Example :
 * [2024-08-08T12:20:05+02:00] ERROR : Error while getting widget preferences for the host monitoring custom view |
 * {"context":{"default":{"back_trace":{"file":"/usr/share/centreon/www/widgets/host-monitoring/src/index.php",
 * "line":100,"class":null,"function":null},"request_infos":{"url":"/centreon/widgets/host-monitoring/src/index.php?
 * widgetId=1&page=0","http_method":"GET","server":"localhost","referrer":"http://localhost/centreon/widgets/host-monit
 * oring/index.php?widgetId=1&customViewId=1"}},"exception":{"exception_type":"Exception","file":"/usr/share/centreon/
 * www/widgets/host-monitoring/src/index.php","line":97,"code":0,"message":"test message","previous":null},"custom":
 * {"widget_id":1}}}
 */

it('test contructor arguments of CentreonLog', function () {
    $loggerTest = new CentreonLog([99 => 'custom.log'], __DIR__ . '/log');
    expect($loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => __DIR__ . '/log/login.log',
            CentreonLog::TYPE_SQL => __DIR__ . '/log/sql-error.log',
            CentreonLog::TYPE_LDAP => __DIR__ . '/log/ldap.log',
            CentreonLog::TYPE_UPGRADE => __DIR__ . '/log/upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => __DIR__ . '/log/plugin-pack-manager.log',
            99 => __DIR__ . '/log/custom.log',
        ]
    );
});

it('test changing the path of an existing log', function () {
    $loggerTest = new CentreonLog();
    $loggerTest->setPathLogFile('/user/test')
        ->pushLogFileHandler(CentreonLog::TYPE_LOGIN, 'login.log')
        ->pushLogFileHandler(CentreonLog::TYPE_SQL, 'sql.log')
        ->pushLogFileHandler(CentreonLog::TYPE_LDAP, 'ldap.log')
        ->pushLogFileHandler(CentreonLog::TYPE_UPGRADE, 'upgrade.log')
        ->pushLogFileHandler(CentreonLog::TYPE_PLUGIN_PACK_MANAGER, 'plugin.log');
    expect($loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => '/user/test/login.log',
            CentreonLog::TYPE_SQL => '/user/test/sql.log',
            CentreonLog::TYPE_LDAP => '/user/test/ldap.log',
            CentreonLog::TYPE_UPGRADE => '/user/test/upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => '/user/test/plugin.log'
        ]
    );
});

it('test adding custom log', function () {
    $loggerTest = new CentreonLog();
    $loggerTest->setPathLogFile('/user/test')
        ->pushLogFileHandler(99, 'custom.log');
    expect($loggerTest->getLogFileHandler())->toHaveKey(99, '/user/test/custom.log');
});

beforeEach(function () {
    if (! file_exists(__DIR__ . '/log')) {
        mkdir(__DIR__ . '/log');
    }
    $this->centreonLogTest = new stdClass();
    $this->centreonLogTest->date = (new DateTime())->format('Y-m-d\T');
    $this->centreonLogTest->pathToLogTest = __DIR__ . '/log';
    $this->centreonLogTest->loggerTest = new CentreonLog(
        customLogFiles: [99 => 'custom.log'],
        pathLogFile: $this->centreonLogTest->pathToLogTest
    );
});

afterEach(function () {
    if (file_exists($this->centreonLogTest->pathToLogTest)) {
        $files = glob($this->centreonLogTest->pathToLogTest);
        foreach ($files as $file) {
            if (is_file($file)) {
                expect(unlink($file))->toBeTrue();
            }
        }
        $successDeleteFile = rmdir($this->centreonLogTest->pathToLogTest);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test log file handler is correct', function () {
    expect($this->centreonLogTest->loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => __DIR__ . '/log/login.log',
            CentreonLog::TYPE_SQL => __DIR__ . '/log/sql-error.log',
            CentreonLog::TYPE_LDAP => __DIR__ . '/log/ldap.log',
            CentreonLog::TYPE_UPGRADE => __DIR__ . '/log/upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => __DIR__ . '/log/plugin-pack-manager.log',
            99 => __DIR__ . '/log/custom.log',
        ]
    );
});

it('test writing the log to the login file', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
    $this->centreonLogTest->loggerTest
        ->log(CentreonLog::TYPE_LOGIN, CentreonLog::LEVEL_ERROR, 'login_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
});

it('test writing the log to the sql file', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/sql-error.log';
    $this->centreonLogTest->loggerTest
        ->log(CentreonLog::TYPE_SQL, CentreonLog::LEVEL_ERROR, 'sql_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'sql_message',
        (__LINE__ - 6)
    );
});

it('test writing the log to the ldap file', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/ldap.log';
    $this->centreonLogTest->loggerTest
        ->log(CentreonLog::TYPE_LDAP, CentreonLog::LEVEL_ERROR, 'ldap_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'ldap_message',
        (__LINE__ - 6)
    );
});

it('test writing the log to the upgrade file', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/upgrade.log';
    $this->centreonLogTest->loggerTest
        ->log(CentreonLog::TYPE_UPGRADE, CentreonLog::LEVEL_ERROR, 'upgrade_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'upgrade_message',
        (__LINE__ - 6)
    );
});

it('test writing the log to the plugin pack manager file', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/plugin-pack-manager.log';
    $this->centreonLogTest->loggerTest
        ->log(CentreonLog::TYPE_PLUGIN_PACK_MANAGER, CentreonLog::LEVEL_ERROR, 'plugin_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'plugin_message',
        (__LINE__ - 6)
    );
});

it('test writing the log to the custom log file', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/custom.log';
    $this->centreonLogTest->loggerTest->log(99, CentreonLog::LEVEL_ERROR, 'custom_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'custom_message',
        (__LINE__ - 6)
    );
});

it('test writing logs with all levels', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
    $this->centreonLogTest->loggerTest->notice(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'notice',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
    $this->centreonLogTest->loggerTest->info(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'info',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
    $this->centreonLogTest->loggerTest->warning(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'warning',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
    $this->centreonLogTest->loggerTest->error(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'error',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
    $this->centreonLogTest->loggerTest->critical(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'critical',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
    $this->centreonLogTest->loggerTest->alert(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'alert',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
    $this->centreonLogTest->loggerTest->emergency(CentreonLog::TYPE_LOGIN, 'login_message');
    testContentLogWithoutContext(
        'emergency',
        $logfile,
        $this->centreonLogTest->date,
        'login_message',
        (__LINE__ - 6)
    );
});

it('test writing logs with a custom context', function () {
    $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
    $this->centreonLogTest->loggerTest
        ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo', 'custom_value2' => 'bar']);
    expect(file_exists($logfile))->toBeTrue();
    $contentLog = file_get_contents($logfile);
    expect($contentLog)->toBeString()->toContain(
        "[{$this->centreonLogTest->date}",
        '] NOTICE : login_message | {"context":{"default":{"back_trace":{"file":"' .
        '/usr/share/centreon/tests/php/www/class/CentreonLogTest.php","line":' . (__LINE__ - 6) . ',"class":null,"function":null},' .
        '"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}},"exception":null,"custom":' .
        '{"custom_value1":"foo","custom_value2":"bar"}}}'
    );
    $successDeleteFile = unlink($logfile);
    expect($successDeleteFile)->toBeTrue();
});

it('test writing logs with a custom context and an exception', function () {
    try {
        throw new RuntimeException('test_message_exception', 99);
    } catch (RuntimeException $e) {
        $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
        $this->centreonLogTest->loggerTest
            ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo'], $e);
        expect(file_exists($logfile))->toBeTrue();
        $contentLog = file_get_contents($logfile);
        expect($contentLog)->toBeString()->toContain(
            "[{$this->centreonLogTest->date}",
            '] NOTICE : login_message | {"context":{"default":{"back_trace":',
            sprintf(
                '"exception":{"exception_type":"RuntimeException","file":"%s","line":%s,"code":99,"message":' .
                '"test_message_exception","previous":null},"custom":{"custom_value1":"foo"}}}',
                $e->getFile(),
                $e->getLine()
            )
        );
        $successDeleteFile = unlink($logfile);
        expect($successDeleteFile)->toBeTrue();
    }
});

/**
 * @param string $level
 * @param string $logfile
 * @param string $date
 * @param string $message
 * @param int $line
 * @return void
 */
function testContentLogWithoutContext(
    string $level,
    string $logfile,
    string $date,
    string $message,
    int $line
): void {
    expect(file_exists($logfile))->toBeTrue();
    $contentLog = file_get_contents($logfile);
    expect($contentLog)->toBeString()->toContain(
        "[{$date}",
        '] ' . strtoupper($level) . ' : ' . $message . ' | {"context":{"default":{"back_trace":{"file":' .
        '"' . __FILE__ . '","line":' . $line . ',"class":null,"function":null},' .
        '"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}},"exception":null,"custom":' .
        'null}}'
    );
    $successDeleteFile = unlink($logfile);
    expect($successDeleteFile)->toBeTrue();
}
