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

namespace Tests\Core\Domain\Common\ValueObject\Identity;

use Core\Domain\Common\Exception\InvalidValueObjectException;
use Core\Domain\Common\ValueObject\Identity\Email;
use Core\Domain\Common\ValueObject\Web\IpAddress;

it('test Email value object is correct', function () {
    $string = "yoyo@toto.fr";
    $email = new Email($string);
    expect($email->getValue())->toBe($string);
});

it('test Email value object with an incorrect email', function () {
    $string = "yoyo";
    $email = new Email($string);
})->throws(InvalidValueObjectException::class);

it('test Email factory with correct email', function () {
    $string = "yoyo@toto.fr";
    $email = Email::createFromString($string);
    expect($email->getValue())->toBe($string);
});

it('test Email factory with incorrect email', function () {
    $string = "yoyo";
    $email = Email::createFromString($string);
})->throws(InvalidValueObjectException::class);

it('test Email value object : get value', function () {
    $string = "yoyo@toto.fr";
    $email = Email::createFromString($string);
    expect($email->getValue())->toBe($string);
});

it('test Email value object : is not empty', function () {
    $string = "yoyo@toto.fr";
    $email = Email::createFromString($string);
    expect($email->isEmpty())->toBeFalse();
});

it('test Email value object : is empty', function () {
    $string = "";
    $email = Email::createFromString($string);
    expect($email->isEmpty())->toBeFalse();
})->throws(InvalidValueObjectException::class);

it('test Email value object : length', function () {
    $email = Email::createFromString("yoyo@toto.fr");
    expect($email->getLength())->toBe(12);
});

it('test Email value object : equal', function () {
    $email1 = Email::createFromString("yoyo@toto.fr");
    $email2 = Email::createFromString("yoyo@toto.fr");
    expect($email1->equals($email2))->toBeTrue();
});

it('test Email value object : not equal', function () {
    $email1 = Email::createFromString("yoyo@toto.fr");
    $email2 = Email::createFromString("yoyo@toto.com");
    expect($email1->equals($email2))->toBeFalse();
});

it('test Email value object : equal with incorrect type', function () {
    $email = Email::createFromString("yoyo@toto.fr");
    $dateTime = new \DateTime();
    $email->equals($dateTime);
})->throws(\TypeError::class);

it('test Email value object : equal with incorrect value object type', function () {
    $email = Email::createFromString("yoyo@toto.fr");
    $ip = new IpAddress("170.0.0.1");
    $email->equals($ip);
})->throws(InvalidValueObjectException::class);

it('test Email value object : magic method toString', function () {
    $email = Email::createFromString("yoyo@toto.fr");
    expect("$email")->toBe("yoyo@toto.fr");
});

it('test Email value object : get local part', function () {
    $email = Email::createFromString("yoyo@toto.fr");
    expect($email->getLocalPart()->getValue())->toBe("yoyo");
});

it('test Email value object : get domain part', function () {
    $email = Email::createFromString("yoyo@toto.fr");
    expect($email->getDomainPart()->getValue())->toBe("toto.fr");
});
