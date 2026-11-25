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
use App\Shared\Infrastructure\Validator\Constraints\NotProvided;
use App\Shared\Infrastructure\Validator\Constraints\NotProvidedValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NotProvidedValidator>
 */
final class NotProvidedValidatorTest extends ConstraintValidatorTestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        parent::setUp();
    }

    public function testAKeyLeftOutRaisesNoViolation(): void
    {
        $this->sendBody('{"data_processing": {"freshness_threshold": 5}}');
        $this->setPropertyPath('dataProcessing.acknowledgmentTimeout');

        $this->validator->validate(null, new NotProvided());

        $this->assertNoViolation();
    }

    #[DataProvider('provideSentValues')]
    public function testAKeySentRaisesAViolationWhateverItsValue(string $value): void
    {
        $this->sendBody('{"data_processing": {"event_handler_args": ' . $value . '}}');
        $this->setPropertyPath('dataProcessing.eventHandlerArgs');

        $constraint = new NotProvided(message: 'Not available here.');
        $this->validator->validate(null, $constraint);

        $this->buildViolation('Not available here.')->atPath('dataProcessing.eventHandlerArgs')->assertRaised();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSentValues(): iterable
    {
        yield 'null' => ['null'];

        yield 'an empty list' => ['[]'];

        yield 'a value' => ['["a"]'];
    }

    public function testTheSameKeyAtTheRootDoesNotMatter(): void
    {
        $this->sendBody('{"event_handler_args": [], "data_processing": {}}');
        $this->setPropertyPath('dataProcessing.eventHandlerArgs');

        $this->validator->validate(null, new NotProvided());

        $this->assertNoViolation();
    }

    public function testWithoutARequestNothingIsChecked(): void
    {
        $this->setPropertyPath('dataProcessing.eventHandlerArgs');

        $this->validator->validate(null, new NotProvided());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NotProvidedValidator(new CurrentRequestPayload($this->requestStack));
    }

    private function sendBody(string $json): void
    {
        $this->requestStack->push(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json']));
    }
}
