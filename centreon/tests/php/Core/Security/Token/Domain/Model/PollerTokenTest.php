<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Security\Token\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\TrimmedString;
use Core\Security\Token\Domain\Model\PollerToken;
use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Domain\Model\TokenTypeEnum;

beforeEach(function (): void {
    $this->createToken = static fn (array $fields = []): PollerToken => new PollerToken(
        ...[
            'name' => new TrimmedString('poller-token'),
            'creatorId' => 12,
            'creatorName' => new TrimmedString('John Doe'),
            'creationDate' => new \DateTimeImmutable(),
            'expirationDate' => (new \DateTimeImmutable())->add(new \DateInterval('P1Y')),
            'isRevoked' => false,
            'tokenString' => 'some-token-string',
            ...$fields,
        ]
    );
});

it('should return properly set token instance', function (): void {
    $token = ($this->createToken)();

    expect($token->getName())->toBe('poller-token')
        ->and($token->getCreatorId())->toBe(12)
        ->and($token->getCreatorName())->toBe('John Doe')
        ->and($token->isRevoked())->toBe(false)
        ->and($token->getToken())->toBe('some-token-string')
        ->and($token->getType())->toBe(TokenTypeEnum::POLLER);
});

foreach (
    [
        'name',
        'creatorName',
    ] as $field
) {
    it(
        "should throw an exception when token {$field} is an empty string",
        fn () => ($this->createToken)([$field => new TrimmedString('    ')])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::notEmptyString("PollerToken::{$field}")->getMessage()
    );
}

foreach (
    [
        'name',
        'creatorName',
    ] as $field
) {
    it("should return a trimmed field {$field}", function () use ($field): void {
        $token = ($this->createToken)([$field => new TrimmedString('  some-text   ')]);
        $valueFromGetter = $token->{'get' . $field}();

        expect($valueFromGetter)->toBe('some-text');
    });
}

// too long fields
foreach (
    [
        'name' => Token::MAX_TOKEN_NAME_LENGTH,
        'creatorName' => Token::MAX_USER_NAME_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when token {$field} is too long",
        fn () => ($this->createToken)([$field => new TrimmedString($tooLong)])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "PollerToken::{$field}")->getMessage()
    );
}
