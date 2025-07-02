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

namespace Tests\Core\Common\Infrastructure\ExceptionLogger;

use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Psr\Log\LogLevel;

beforeEach(function (): void {
    $this->logFilePath = __DIR__ . '/log';
    $this->logFileName = 'test.log';
    $this->logPathFileName = $this->logFilePath . '/test.log';

    if (! file_exists($this->logFilePath)) {
        mkdir($this->logFilePath);
    }
    $this->logger = new LoggerStub($this->logPathFileName);
    $this->exceptionLogger = new ExceptionLogger($this->logger);
});

afterEach(function (): void {
    if (file_exists($this->logPathFileName)) {
        expect(unlink($this->logPathFileName))->toBeTrue();
        $successDeleteFile = rmdir($this->logFilePath);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test log a native exception', function () {
    $this->exceptionLogger->log(new \LogicException('logic_exception_message'));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":null,"exception":{"exceptions":[{"type":"LogicException","message":"logic_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 4) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"}],"traces":[{"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","type":"->"}'
    );
});

it('test log an exception that extends BusinessLogicException without context and previous', function () {
    $this->exceptionLogger->log(new RepositoryException('repository_exception_message'));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":{"from_exception":[]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 4) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"}],"traces":[{"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","type":"->"}'
    );
});

it('test log an exception that extends BusinessLogicException with context without previous', function () {
    $this->exceptionLogger->log(new RepositoryException('repository_exception_message', ['contact' => 1]));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":{"from_exception":[{"contact":1}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 4) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"}],"traces":[{"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","type":"->"}'
    );
});

it(
    'test log an exception that extends BusinessLogicException with context with a previous (native exception)',
    function () {
        $this->exceptionLogger->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new \LogicException('logic_exception_message')
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":[{"contact":1}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"},{"type":"LogicException","message":"logic_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 6) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"}],"traces":[{"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","type":"->"}'
        );
    }
);


it(
    'test log an exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException',
    function () {
        $this->exceptionLogger->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException('repository_exception_message_2')
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":[{"contact":1}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"},{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"' . __FILE__ . '","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"}],"traces":[{"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","type":"->"}'
        );
    }
);

it(
    'test log an exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context',
    function () {
        $this->exceptionLogger->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException('repository_exception_message_2', ['contact' => 2])
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":[{"contact":1},{"contact":2}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"},{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"' . __FILE__ . '","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}"}],"traces":[{"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\ExceptionLoggerTest","type":"->"}'
        );
    }
);

it(
    'test log an exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context and a previous exception',
    function () {
        function testExceptionLogger(int $int, string $string): void
        {
            throw new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException(
                    'repository_exception_message_2',
                    ['contact' => 2],
                    new \LogicException('logic_exception_message')
                )
            );
        }
        try {
            testExceptionLogger(1, 'string');
        } catch (RepositoryException $e) {
            $this->exceptionLogger->log($e, ['name' => 'John Doe', 'age' => 42], LogLevel::CRITICAL);
        }

        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain('test_exception_logger.CRITICAL: repository_exception_message')
            ->and($contentLog)->toContain('{"custom":{"name":"John Doe","age":42,"from_exception":[{"contact":1},{"contact":2}]},"exception":{"exceptions":[{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 19) . ',"code":1,"class":null,"method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\testExceptionLogger"},{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"' . __FILE__ . '","line":' . (__LINE__ - 16) . ',"code":1,"class":null,"method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\testExceptionLogger"},{"type":"LogicException","message":"logic_exception_message","file":"' . __FILE__ . '","line":' . (__LINE__ - 13) . ',"code":0,"class":null,"method":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\testExceptionLogger"}],"traces":[{"file":"' . __FILE__ . '","line":' . (__LINE__ - 8) . ',"function":"Tests\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionLogger\\\\testExceptionLogger"}');
        }
);
