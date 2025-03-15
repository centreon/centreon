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
use Core\Common\Infrastructure\ExceptionHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

beforeEach(function (): void {
    $this->logFilePath = __DIR__ . '/log';
    $this->logFileName = 'test.log';
    $this->logPathFileName = $this->logFilePath . '/test.log';

    if (! file_exists($this->logFilePath)) {
        mkdir($this->logFilePath);
    }
    $this->logger = new Logger('test_exception_logger');
    $this->logger->pushHandler(new StreamHandler($this->logPathFileName));
    $this->exceptionHandler = new ExceptionHandler($this->logger);
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
        '{"exception":{"type":"LogicException","message":"logic_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 4) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null,"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
    );
});

it('test log a exception that extends BusinessLogicException without context and previous', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message'));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":{"from_exception":{"previous":null}},"exception":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 4) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null,"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
    );
});

it('test log a exception that extends BusinessLogicException with context without previous', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message', ['contact' => 1]));
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        '{"custom":{"from_exception":{"contact":1,"previous":null}},"exception":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 4) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null,"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
    );});

it(
    'test log a exception that extends BusinessLogicException with context with a previous (native exception)',
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
            '{"custom":{"from_exception":{"contact":1,"previous":null}},"exception":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":{"type":"LogicException","message":"logic_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 6) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null},"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
        );
    }
);

it(
    'test log a exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException',
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
            '{"custom":{"from_exception":{"contact":1,"previous":{"previous":null}}},"exception":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null},"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
        );
    }
);

it(
    'test log a exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context',
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
            '{"custom":{"from_exception":{"contact":1,"previous":{"contact":2,"previous":null}}},"exception":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null},"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
        );
    }
);

it(
    'test log a exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context and a previous exception',
    function () {
        $this->exceptionHandler->log(
            new RepositoryException(
                'repository_exception_message',
                ['contact' => 1],
                new RepositoryException('repository_exception_message_2', ['contact' => 2], new LogicException('logic_exception_message'))
            )
        );
        expect(file_exists($this->logPathFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logPathFileName);
        expect($contentLog)->toContain(
            '{"custom":{"from_exception":{"contact":1,"previous":{"contact":2,"previous":null}}},"exception":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 9) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":{"type":"Core\\\\Common\\\\Domain\\\\Exception\\\\RepositoryException","message":"repository_exception_message_2","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 6) . ',"code":1,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":{"type":"LogicException","message":"logic_exception_message","file":"/usr/share/centreon/tests/php/Core/Common/Infrastructure/ExceptionHandlerTest.php","line":' . (__LINE__ - 6) . ',"code":0,"class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","method":"{closure}","previous":null}},"trace":[{"function":"{closure}","class":"P\\\\Tests\\\\php\\\\Core\\\\Common\\\\Infrastructure\\\\ExceptionHandlerTest","type":"->","args":"[][...]"}'
        );
    }
);
