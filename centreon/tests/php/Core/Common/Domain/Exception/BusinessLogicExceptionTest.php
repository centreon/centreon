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

use Core\Common\Domain\Exception\BusinessLogicException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;

it('test with a basic context from a repository exception', function () {
    try {
        throw new LogicException('logic_message', 100);
    } catch (LogicException $logicException) {
        try {
            throw new RepositoryException(
                message: 'repository_message',
                context: ['name' => 'John', 'age' => 42],
                previous: $logicException
            );
        } catch (BusinessLogicException $exception) {
            expect($exception->getMessage())->toBe('repository_message')
                ->and($exception->getCode())->toBe(1)
                ->and($exception->getPrevious())->toBeInstanceOf(LogicException::class)
                ->and($exception->getContext())->toBeArray()
                ->and($exception->getContext())->toBe(
                    [
                        'type' => RepositoryException::class,
                        'message' => 'repository_message',
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'code' => 1,
                        'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                        'method' => '{closure}',
                        'previous' => [
                            'type' => LogicException::class,
                            'message' => 'logic_message',
                            'file' => $exception->getPrevious()->getFile(),
                            'line' => $exception->getPrevious()->getLine(),
                            'code' => 100,
                            'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                            'method' => '{closure}',
                        ],
                        'context' => [
                            'name' => 'John',
                            'age' => 42,
                            'previous' => null
                        ],
                    ]
                );
        }
    }
});

it('test with a business context from a repository exception', function () {
    try {
        throw new CollectionException('collection_message', ['name' => 'Anna', 'age' => 25]);
    } catch (CollectionException $collectionException) {
        try {
            throw new RepositoryException(
                message: 'repository_message',
                context: ['name' => 'John', 'age' => 42],
                previous: $collectionException
            );
        } catch (BusinessLogicException $exception) {
            expect($exception->getMessage())->toBe('repository_message')
                ->and($exception->getCode())->toBe(1)
                ->and($exception->getPrevious())->toBeInstanceOf(CollectionException::class)
                ->and($exception->getContext())->toBeArray()
                ->and($exception->getContext())->toBe(
                    [
                        'type' => RepositoryException::class,
                        'message' => 'repository_message',
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'code' => 1,
                        'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                        'method' => '{closure}',
                        'previous' => [
                            'type' => CollectionException::class,
                            'message' => 'collection_message',
                            'file' => $exception->getPrevious()->getFile(),
                            'line' => $exception->getPrevious()->getLine(),
                            'code' => 0,
                            'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                            'method' => '{closure}',
                            'previous' => null,
                        ],
                        'context' => [
                            'name' => 'John',
                            'age' => 42,
                            'previous' => [
                                'name' => 'Anna',
                                'age' => 25,
                                'previous' => null
                            ]
                        ],
                    ]
                );
        }
    }
});

it('test with a business context with previous from a repository exception', function () {
    try {
        try {
            throw new LogicException('logic_message', 100);
        } catch (LogicException $logicException) {
            throw new CollectionException(
                message: 'collection_message',
                context: ['name' => 'Anna', 'age' => 25],
                previous: $logicException
            );
        }
    } catch (CollectionException $collectionException) {
        try {
            throw new RepositoryException(
                message: 'repository_message',
                context: ['name' => 'John', 'age' => 42],
                previous: $collectionException
            );
        } catch (BusinessLogicException $exception) {
            expect($exception->getMessage())->toBe('repository_message')
                ->and($exception->getCode())->toBe(1)
                ->and($exception->getPrevious())->toBeInstanceOf(CollectionException::class)
                ->and($exception->getContext())->toBeArray()
                ->and($exception->getContext())->toBe(
                    [
                        'type' => RepositoryException::class,
                        'message' => 'repository_message',
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'code' => 1,
                        'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                        'method' => '{closure}',
                        'previous' => [
                            'type' => CollectionException::class,
                            'message' => 'collection_message',
                            'file' => $exception->getPrevious()->getFile(),
                            'line' => $exception->getPrevious()->getLine(),
                            'code' => 0,
                            'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                            'method' => '{closure}',
                            'previous' => [
                                'type' => LogicException::class,
                                'message' => 'logic_message',
                                'file' => $exception->getPrevious()->getPrevious()->getFile(),
                                'line' => $exception->getPrevious()->getPrevious()->getLine(),
                                'code' => 100,
                                'class' => 'P\Tests\php\Core\Common\Domain\Exception\BusinessLogicExceptionTest',
                                'method' => '{closure}',
                            ],
                        ],
                        'context' => [
                            'name' => 'John',
                            'age' => 42,
                            'previous' => [
                                'name' => 'Anna',
                                'age' => 25,
                                'previous' => null
                            ]
                        ],
                    ]
                );
        }
    }
});
