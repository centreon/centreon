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
 * {"custom":{"widget_id":1},"exception":{"exception_type":"Exception","file":"/usr/share/centreon/
 * www/widgets/host-monitoring/src/index.php","line":97,"code":0,"message":"test message","previous":null},"default":
 * {"request_infos":{"uri":"/centreon/widgets/host-monitoring/src/index.php?widgetId=1&page=0","http_method":"GET","server":"localhost"}}
 */

beforeEach(function (): void {
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

afterEach(function (): void {
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

it('test contructor arguments of CentreonLog', function (): void {
    $loggerTest = new CentreonLog([99 => 'custom.log'], __DIR__ . '/log');
    expect($loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => __DIR__ . '/log/login.log',
            CentreonLog::TYPE_SQL => __DIR__ . '/log/sql-error.log',
            CentreonLog::TYPE_LDAP => __DIR__ . '/log/ldap.log',
            CentreonLog::TYPE_UPGRADE => __DIR__ . '/log/upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => __DIR__ . '/log/plugin-pack-manager.log',
            CentreonLog::TYPE_BUSINESS_LOG => __DIR__ . '/log/centreon-web.log',
            99 => __DIR__ . '/log/custom.log',
        ]
    );
});

it('test changing the path of an existing log', function (): void {
    $loggerTest = new CentreonLog();
    $loggerTest->setPathLogFile('/user/test')
        ->pushLogFileHandler(CentreonLog::TYPE_LOGIN, 'login.log')
        ->pushLogFileHandler(CentreonLog::TYPE_SQL, 'sql.log')
        ->pushLogFileHandler(CentreonLog::TYPE_LDAP, 'ldap.log')
        ->pushLogFileHandler(CentreonLog::TYPE_UPGRADE, 'upgrade.log')
        ->pushLogFileHandler(CentreonLog::TYPE_PLUGIN_PACK_MANAGER, 'plugin.log')
        ->pushLogFileHandler(CentreonLog::TYPE_BUSINESS_LOG, 'centreon-web.log');
    expect($loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => '/user/test/login.log',
            CentreonLog::TYPE_SQL => '/user/test/sql.log',
            CentreonLog::TYPE_LDAP => '/user/test/ldap.log',
            CentreonLog::TYPE_UPGRADE => '/user/test/upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => '/user/test/plugin.log',
            CentreonLog::TYPE_BUSINESS_LOG => '/user/test/centreon-web.log'
        ]
    );
});

it('test adding custom log', function (): void {
    $loggerTest = new CentreonLog();
    $loggerTest->setPathLogFile('/user/test')
        ->pushLogFileHandler(99, 'custom.log');
    expect($loggerTest->getLogFileHandler())->toHaveKey(99, '/user/test/custom.log');
});

it('test log file handler is correct', function (): void {
    expect($this->centreonLogTest->loggerTest->getLogFileHandler())->toEqual(
        [
            CentreonLog::TYPE_LOGIN => __DIR__ . '/log/login.log',
            CentreonLog::TYPE_SQL => __DIR__ . '/log/sql-error.log',
            CentreonLog::TYPE_LDAP => __DIR__ . '/log/ldap.log',
            CentreonLog::TYPE_UPGRADE => __DIR__ . '/log/upgrade.log',
            CentreonLog::TYPE_PLUGIN_PACK_MANAGER => __DIR__ . '/log/plugin-pack-manager.log',
            99 => __DIR__ . '/log/custom.log',
            CentreonLog::TYPE_BUSINESS_LOG => __DIR__ . '/log/centreon-web.log',
        ]
    );
});

it('test writing the log to the login file', function (): void {
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

it('test writing the log to the sql file', function (): void {
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

it('test writing the log to the ldap file', function (): void {
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

it('test writing the log to the upgrade file', function (): void {
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

it('test writing the log to the plugin pack manager file', function (): void {
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

it('test writing the log to the custom log file', function (): void {
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

it('test writing logs with all levels', function (): void {
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

it('test writing logs with a custom context', function (): void {
    $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
    $this->centreonLogTest->loggerTest
        ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo', 'custom_value2' => 'bar']);
    expect(file_exists($logfile))->toBeTrue();
    $contentLog = file_get_contents($logfile);
    expect($contentLog)->toBeString()->toContain(
        "[{$this->centreonLogTest->date}",
        '] NOTICE : login_message | {"custom":{"custom_value1":"foo","custom_value2":"bar"},"exception":null,"default":' .
        '{"request_infos":{"uri":null,"http_method":null,"server":null}}}'
    );
    $successDeleteFile = unlink($logfile);
    expect($successDeleteFile)->toBeTrue();
});

it('test writing logs with a custom context and an exception', function (): void {
    try {
        throw new RuntimeException('test_message_exception', 99);
    } catch (RuntimeException $e) {
        $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
        $this->centreonLogTest->loggerTest
            ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo'], $e);
        expect(file_exists($logfile))->toBeTrue();
        $contentLog = file_get_contents($logfile);
        expect($contentLog)->toBeString()->toContain(
            sprintf(
                '] NOTICE : login_message | {"custom":{"custom_value1":"foo"},"exception":{"exceptions":' .
                '[{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"}],' .
                '"traces":[{"function":"%s","class":"%s","type":"%s"},',
                'RuntimeException',
                'test_message_exception',
                $e->getFile(),
                $e->getLine(),
                99,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "{closure}",
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "->"
            ),
            '"default":{"request_infos":{"uri":null,"http_method":null,"server":null}}}'
        );
        $successDeleteFile = unlink($logfile);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test writing logs with a custom context and a native exception with a previous (native exception)', function (): void {
    try {
        $previous = new LogicException('test_message_exception_previous', 98);
        throw new RuntimeException('test_message_exception', 99, $previous);
    } catch (RuntimeException $e) {
        $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
        $this->centreonLogTest->loggerTest
            ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo'], $e);
        expect(file_exists($logfile))->toBeTrue();
        $contentLog = file_get_contents($logfile);
        expect($contentLog)->toBeString()->toContain(
            sprintf(
                '] NOTICE : login_message | {"custom":{"custom_value1":"foo"},' .
                '"exception":{"exceptions":[{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"},' .
                '{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"}],' .
                '"traces":[{"function":"%s","class":"%s","type":"%s"},',
                "RuntimeException",
                "test_message_exception",
                $e->getFile(),
                $e->getLine(),
                99,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "LogicException",
                "test_message_exception_previous",
                $e->getPrevious()->getFile(),
                $e->getPrevious()->getLine(),
                98,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "{closure}",
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "->"
            ),
            '"default":{"request_infos":{"uri":null,"http_method":null,"server":null}}',
        );
        $successDeleteFile = unlink($logfile);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test writing logs with a custom context and an exception (BusinessLogicException with context) with a previous exception (native exception)', function (): void {
    try {
        $previous = new LogicException('test_message_exception_previous', 99);
        throw new CentreonDbException('test_message_exception', ['contact' => 1], $previous);
    } catch (CentreonDbException $e) {
        $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
        $this->centreonLogTest->loggerTest
            ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo'], $e);
        expect(file_exists($logfile))->toBeTrue();
        $contentLog = file_get_contents($logfile);
        expect($contentLog)->toBeString()->toContain(
            sprintf(
                '] NOTICE : login_message | {"custom":{"custom_value1":"foo","from_exception":[{"contact":1}]},' .
                '"exception":{"exceptions":[{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"},' .
                '{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"}],' .
                '"traces":[{"function":"%s","class":"%s","type":"%s"},',
                "CentreonDbException",
                "test_message_exception",
                $e->getFile(),
                $e->getLine(),
                1,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "LogicException",
                "test_message_exception_previous",
                $e->getPrevious()->getFile(),
                $e->getPrevious()->getLine(),
                99,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "{closure}",
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "->"
            ),
            '"default":{"request_infos":{"uri":null,"http_method":null,"server":null}}',
        );
        $successDeleteFile = unlink($logfile);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test writing logs with a custom context and an exception (BusinessLogicException with context) with a previous exception (BusinessLogicException with context) which have a native exception as previous', function (): void {
    try {
        $nativePrevious = new LogicException('test_message_native_exception_previous', 99);
        $previous = new CentreonDbException('test_message_exception_previous', ['id' => 1, 'name' => 'John', 'age' => 48], $nativePrevious);
        throw new StatisticException('test_message_exception', ['X' => 100.36, 'Y' => 888, 'graph' => true], $previous);
    } catch (StatisticException $e) {
        $logfile = $this->centreonLogTest->pathToLogTest . '/login.log';
        $this->centreonLogTest->loggerTest
            ->notice(CentreonLog::TYPE_LOGIN, 'login_message', ['custom_value1' => 'foo', 'custom_value2' => 'bar'], $e);
        expect(file_exists($logfile))->toBeTrue();
        $contentLog = file_get_contents($logfile);
        expect($contentLog)->toBeString()->toContain(
            sprintf(
                '] NOTICE : login_message | {"custom":{"custom_value1":"foo","custom_value2":"bar","from_exception":' .
                '[{"X":100.36,"Y":888,"graph":true},{"id":1,"name":"John","age":48}]},' .
                '"exception":{"exceptions":[{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"},' .
                '{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"},' .
                '{"type":"%s","message":"%s","file":"%s","line":%s,"code":%s,"class":"%s","method":"%s"}],' .
                '"traces":[{"function":"%s","class":"%s","type":"%s"},',
                "StatisticException",
                "test_message_exception",
                $e->getFile(),
                $e->getLine(),
                0,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "CentreonDbException",
                "test_message_exception_previous",
                $e->getPrevious()->getFile(),
                $e->getPrevious()->getLine(),
                1,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "LogicException",
                "test_message_native_exception_previous",
                $e->getPrevious()->getPrevious()->getFile(),
                $e->getPrevious()->getPrevious()->getLine(),
                99,
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "{closure}",
                "{closure}",
                "P\\\\Tests\\\\php\\\\www\\\\class\\\\CentreonLogTest",
                "->"
            ),
            '"default":{"request_infos":{"uri":null,"http_method":null,"server":null}}',
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
 *
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
        '] ' . strtoupper($level) . ' : ' . $message . ' | {"custom":null,"exception":null,"default":' .
        '{"request_infos":{"uri":null,"http_method":null,"server":null}}}'
    );
    $successDeleteFile = unlink($logfile);
    expect($successDeleteFile)->toBeTrue();
}
