<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\ExceptionLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;

beforeEach(function (): void {
    $this->logFilePath = __DIR__ . '/log';
    $this->logFileName = 'test.log';
    $this->logPathFileName = $this->logFilePath . '/test.log';

    if (! file_exists($this->logFilePath)) {
        mkdir($this->logFilePath);
    }
    $this->logger = new Logger('test_exception_logger');
    $this->logger->pushHandler(new StreamHandler($this->logPathFileName));
    $this->exceptionHandler = new ExceptionLogger($this->logger);
});

afterEach(function (): void {
    if (file_exists($this->logPathFileName)) {
        expect(unlink($this->logPathFileName))->toBeTrue();
        $successDeleteFile = rmdir($this->logFilePath);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test log a native exception', function () {
    $this->exceptionHandler->log(new LogicException('logic_exception_message'));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":null,"exception":{"exceptions":[{"type":"LogicException","message":"logic_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 4) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"}],"traces":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","type":"->"}'
    );
});

it('test log an exception that extends BusinessLogicException without context and previous', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message'));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":{"from_exception":[]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 4) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"}],"traces":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","type":"->"}'
    );
});

it('test log an exception that extends BusinessLogicException with context without previous', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message', ['contact' => 1]));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":{"from_exception":[{"contact":1}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 4) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"}],"traces":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","type":"->"}'
    );
});

it(
    'test log an exception that extends BusinessLogicException with context with a previous (native exception)',
    function () {
        $this->exceptionHandler->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new LogicException('logic_exception_message')
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":[{"contact":1}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"},{"type":"LogicException","message":"logic_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 6) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"}],"traces":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","type":"->"}'
        );
    }
);


it(
    'test log an exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException',
    function () {
        $this->exceptionHandler->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException('repository_exception_message_2')
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":[{"contact":1}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"},{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"' . __FILE__ . '","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"}],"traces":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","type":"->"}'
        );
    }
);

it(
    'test log an exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context',
    function () {
        $this->exceptionHandler->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException('repository_exception_message_2', ['contact' => 2])
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":[{"contact":1},{"contact":2}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"},{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"' . __FILE__ . '","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","method":"{closure}"}],"traces":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLoggerTest","type":"->"}'
        );
    }
);

it(
    'test log an exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context and a previous exception',
    function () {
        function testExceptionHandler(int $int, string $string): void
        {
            throw new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException(
                    'repository_exception_message_2',
                    ['contact' => 2],
                    new LogicException('logic_exception_message')
                )
            );
        }
        try {
            testExceptionHandler(1, 'string');
        } catch (RepositoryException $e) {
            $this->exceptionHandler->log($e, ['name' => 'John Doe', 'age' => 42], LogLevel::CRITICAL);
        }

        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain('test_exception_logger.CRITICAL: repository_exception_message')
            ->and($contentLog)->toContain('{"custom":{"name":"John Doe","age":42,"from_exception":[{"contact":1},{"contact":2}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 19) . ',"code":1,"class":null,"method":"testExceptionHandler"},{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"' . __FILE__ . '","line":' . (__LINE__ - 16) . ',"code":1,"class":null,"method":"testExceptionHandler"},{"type":"LogicException","message":"logic_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 13) . ',"code":0,"class":null,"method":"testExceptionHandler"}],"traces":[{"file":"' . __FILE__ . '","line":' . (__LINE__ - 8) . ',"function":"testExceptionHandler"}');
        }
);
