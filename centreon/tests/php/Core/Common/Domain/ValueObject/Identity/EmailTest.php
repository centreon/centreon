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

namespace Tests\Core\Common\Domain\ValueObject\Identity;

use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\Identity\Email;
use Core\Common\Domain\ValueObject\Web\IpAddress;

it('test Email value object is correct', function () {
    $string = "yoyo@toto.fr";
    $email = new Email($string);
    expect($email->getValue())->toBe($string);
});

it('test Email value object : with special characters', function () {
    $email = new Email("user+tag@example.com");
    expect($email->getValue())->toBe("user+tag@example.com");
});

it('test Email value object : with quoted local part', function () {
    $email = new Email('"test.email"@example.com');
    expect($email->getValue())->toBe('"test.email"@example.com');
});

it('test Email value object : with an incorrect email', function () {
    $string = "yoyo";
    $email = new Email($string);
})->throws(ValueObjectException::class);

it('test Email value object : get value', function () {
    $string = "yoyo@toto.fr";
    $email = new Email($string);
    expect($email->getValue())->toBe($string);
});

it('test Email value object : is not empty', function () {
    $string = "yoyo@toto.fr";
    $email = new Email($string);
    expect($email->isEmpty())->toBeFalse();
});

it('test Email value object : is empty', function () {
    $string = "";
    $email = new Email($string);
})->throws(ValueObjectException::class);

it('test Email value object : length', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->length())->toBe(12);
});


it('test Email value object : to uppercase', function () {
    $email = new Email("yoyo@toto.fr");
    $newEmail = $email->toUpperCase();
    expect($email)
        ->toBeInstanceOf(Email::class)
        ->and($email->getValue())
        ->toBe("yoyo@toto.fr")
        ->and($newEmail)
        ->toBeInstanceOf(Email::class)
        ->and($newEmail->getValue())
        ->toBe("YOYO@TOTO.FR");
});

it('test Email value object : to lowercase', function () {
    $email = new Email("YOYO@TOTO.FR");
    $newEmail = $email->toLowerCase();
    expect($email)
        ->toBeInstanceOf(Email::class)
        ->and($email->getValue())
        ->toBe("YOYO@TOTO.FR")
        ->and($newEmail)
        ->toBeInstanceOf(Email::class)
        ->and($newEmail->getValue())
        ->toBe("yoyo@toto.fr");
});

it('test Email value object : starts with', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->startsWith("yoyo"))->toBeTrue();
});

it('test Email value object : not starts with', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->startsWith("bar"))->toBeFalse();
});

it('test Email value object : ends with', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->endsWith("fr"))->toBeTrue();
});

it('test Email value object : not ends with', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->endsWith("bar"))->toBeFalse();
});

it('test Email value object : replace', function () {
    $email = new Email("yoyo@toto.fr");
    $newEmail = $email->replace("yoyo", "yaya");
    expect($email)
        ->toBeInstanceOf(Email::class)
        ->and($email->getValue())
        ->toBe("yoyo@toto.fr")
        ->and($newEmail)
        ->toBeInstanceOf(Email::class)
        ->and($newEmail->getValue())
        ->toBe("yaya@toto.fr");
});

it('test Email value object : contains', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->contains("yoyo"))->toBeTrue();
});

it('test Email value object : not contains', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->contains("bar"))->toBeFalse();
});

it('test Email value object : append', function () {
    $email = new Email("yoyo@toto.fr");
    $newEmail = $email->append("ance");
    expect($email)
        ->toBeInstanceOf(Email::class)
        ->and($email->getValue())
        ->toBe("yoyo@toto.fr")
        ->and($newEmail)
        ->toBeInstanceOf(Email::class)
        ->and($newEmail->getValue())
        ->toBe("yoyo@toto.france");
});

it('test Email value object : equal', function () {
    $email1 = new Email("yoyo@toto.fr");
    $email2 = new Email("yoyo@toto.fr");
    expect($email1->equals($email2))->toBeTrue();
});

it('test Email value object : not equal', function () {
    $email1 = new Email("yoyo@toto.fr");
    $email2 = new Email("yoyo@toto.com");
    expect($email1->equals($email2))->toBeFalse();
});

it('test Email value object : equal with incorrect type', function () {
    $email = new Email("yoyo@toto.fr");
    $dateTime = new \DateTime();
    $email->equals($dateTime);
})->throws(\TypeError::class);

it('test Email value object : equal with incorrect value object type', function () {
    $email = new Email("yoyo@toto.fr");
    $ip = new IpAddress("170.0.0.1");
    $email->equals($ip);
})->throws(ValueObjectException::class);

it('test Email value object : magic method toString', function () {
    $email = new Email("yoyo@toto.fr");
    expect("$email")->toBe("yoyo@toto.fr");
});

it('test Email value object : get local part', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->getLocalPart()->getValue())->toBe("yoyo");
});

it('test Email value object : get domain part', function () {
    $email = new Email("yoyo@toto.fr");
    expect($email->getDomainPart()->getValue())->toBe("toto.fr");
});
