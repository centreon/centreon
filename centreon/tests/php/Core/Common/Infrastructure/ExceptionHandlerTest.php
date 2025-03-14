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

use Centreon\Domain\Log\Logger;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\ExceptionHandler;

beforeEach(function (): void {
    if (! file_exists(__DIR__ . '/log')) {
        mkdir(__DIR__ . '/log');
    }
    $this->logger = Logger::create();
    $this->logFileName = 'test.log';
    $this->exceptionHandler = new ExceptionHandler($this->logger);
});

afterEach(function (): void {
    if (file_exists($this->logFileName)) {
        expect(unlink($this->logFileName))->toBeTrue();
        $successDeleteFile = rmdir(__DIR__ . '/log');
        expect($successDeleteFile)->toBeTrue();
    }
});

it('test log a native exception', function () {
    $this->exceptionHandler->log(new LogicException('logic_exception_message'));
    expect(file_exists($this->logFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logFileName);
    expect($contentLog)->toContain('logic_exception_message');
});

it('test log a exception that extends BusinessLogicException without context and previous', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message'));
    expect(file_exists($this->logFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logFileName);
    expect($contentLog)->toContain('repository_exception_message');
});

it('test log a exception that extends BusinessLogicException with context without previous', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message', ['contact' => 1]));
    expect(file_exists($this->logFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logFileName);
    expect($contentLog)->toContain('repository_exception_message');
});

it('test log a exception that extends BusinessLogicException with context with a previous (native exception)', function () {
    $this->exceptionHandler->log(new RepositoryException('repository_exception_message', ['contact' => 1], new LogicException('logic_exception_message')));
    expect(file_exists($this->logFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logFileName);
    expect($contentLog)->toContain('repository_exception_message')
        ->and($contentLog)->toContain('logic_exception_message');
});

it(
    'test log a exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException',
    function () {
        $this->exceptionHandler->log(new RepositoryException('repository_exception_message', ['contact' => 1], new RepositoryException('repository_exception_message_2')));
        expect(file_exists($this->logFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logFileName);
        expect($contentLog)->toContain('repository_exception_message')
            ->and($contentLog)->toContain('repository_exception_message_2');
    }
);

it(
    'test log a exception that extends BusinessLogicException with context and a previous that extends a BusinessLogicException which has context',
    function () {
        $this->exceptionHandler->log(new RepositoryException('repository_exception_message', ['contact' => 1], new RepositoryException('repository_exception_message_2', ['contact' => 2])));
        expect(file_exists($this->logFileName))->toBeTrue();
        $contentLog = file_get_contents($this->logFileName);
        expect($contentLog)->toContain('repository_exception_message')
            ->and($contentLog)->toContain('repository_exception_message_2');
    }
);
