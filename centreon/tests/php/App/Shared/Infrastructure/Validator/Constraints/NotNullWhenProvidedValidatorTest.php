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

namespace Tests\App\Shared\Infrastructure\Validator\Constraints;

use App\Shared\Infrastructure\ApiPlatform\CurrentRequestPayload;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvidedValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NotNullWhenProvidedValidator>
 */
final class NotNullWhenProvidedValidatorTest extends ConstraintValidatorTestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        parent::setUp();
    }

    public function testAKeyLeftOutRaisesNoViolation(): void
    {
        $this->sendBody('{"alias": "front"}');
        $this->setPropertyPath('name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testAKeyWithAValueRaisesNoViolation(): void
    {
        $this->sendBody('{"name": "server-01"}');
        $this->setPropertyPath('name');

        $this->validator->validate('server-01', new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testAKeySentAsNullRaisesAViolation(): void
    {
        $this->sendBody('{"name": null}');
        $this->setPropertyPath('name');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->atPath('name')->assertRaised();
    }

    public function testACamelCasePropertyIsLookedUpUnderItsSnakeCaseKey(): void
    {
        $this->sendBody('{"poller_id": null}');
        $this->setPropertyPath('pollerId');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->atPath('pollerId')->assertRaised();
    }

    public function testANestedPropertyIsLookedUpInsideItsSubObject(): void
    {
        $this->sendBody('{"check_options": {"args": null}}');
        $this->setPropertyPath('checkOptions.args');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->atPath('checkOptions.args')->assertRaised();
    }

    public function testANestedKeyIsNotConfusedWithTheSameKeyAtTheRoot(): void
    {
        $this->sendBody('{"args": null, "check_options": {"command_id": 3}}');
        $this->setPropertyPath('checkOptions.args');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testAPropertyOfAnElementOfAListIsLookedUpInThatElement(): void
    {
        $this->sendBody('{"macros": [{"name": "TOKEN", "value": "a"}, {"name": null}]}');
        $this->setPropertyPath('macros[1].name');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->atPath('macros[1].name')->assertRaised();
    }

    public function testAPropertyOfAnotherElementOfTheListDoesNotMatter(): void
    {
        $this->sendBody('{"macros": [{"name": "TOKEN"}, {"name": null}]}');
        $this->setPropertyPath('macros[0].name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testAnElementSentAsNullInAListRaisesAViolation(): void
    {
        $this->sendBody('{"contact_ids": [3, null]}');
        $this->setPropertyPath('contactIds[1]');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->atPath('contactIds[1]')->assertRaised();
    }

    public function testAnotherKeySentAsNullDoesNotMatter(): void
    {
        $this->sendBody('{"alias": null}');
        $this->setPropertyPath('name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testWithoutARequestNothingIsChecked(): void
    {
        $this->setPropertyPath('name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NotNullWhenProvidedValidator(new CurrentRequestPayload($this->requestStack));
    }

    private function sendBody(string $json): void
    {
        $this->requestStack->push(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json']));
    }
}
