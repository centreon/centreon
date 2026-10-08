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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Validator;

use App\MonitoringConfiguration\Infrastructure\Validator\NotNullWhenProvided;
use App\MonitoringConfiguration\Infrastructure\Validator\NotNullWhenProvidedValidator;
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
        $this->setProperty($this->subject(), 'name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testAKeyWithAValueRaisesNoViolation(): void
    {
        $this->sendBody('{"name": "server-01"}');
        $this->setProperty($this->subject(), 'name');

        $this->validator->validate('server-01', new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testAKeySentAsNullRaisesAViolation(): void
    {
        $this->sendBody('{"name": null}');
        $this->setProperty($this->subject(), 'name');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testACamelCasePropertyIsLookedUpUnderItsSnakeCaseKey(): void
    {
        $this->sendBody('{"poller_id": null}');
        $this->setProperty($this->subject(), 'pollerId');

        $constraint = new NotNullWhenProvided();
        $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAnotherKeySentAsNullDoesNotMatter(): void
    {
        $this->sendBody('{"alias": null}');
        $this->setProperty($this->subject(), 'name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    public function testWithoutARequestNothingIsChecked(): void
    {
        $this->setProperty($this->subject(), 'name');

        $this->validator->validate(null, new NotNullWhenProvided());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NotNullWhenProvidedValidator($this->requestStack);
    }

    private function subject(): object
    {
        return new class () {
            public ?string $name = null;

            public ?int $pollerId = null;
        };
    }

    private function sendBody(string $json): void
    {
        $this->requestStack->push(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json']));
    }
}
