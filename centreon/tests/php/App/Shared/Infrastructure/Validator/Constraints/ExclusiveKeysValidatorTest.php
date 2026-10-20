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
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeysValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ExclusiveKeysValidator>
 */
final class ExclusiveKeysValidatorTest extends ConstraintValidatorTestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        parent::setUp();
    }

    public function testNoKeySentIsFine(): void
    {
        $this->sendBody('{"alias": "front"}');
        $this->setPropertyPath('');

        $this->validator->validate(new \stdClass(), $this->constraint());

        $this->assertNoViolation();
    }

    public function testOneKeyOfASetIsFine(): void
    {
        $this->sendBody('{"host_group_ids_to_add": []}');
        $this->setPropertyPath('');

        $this->validator->validate(new \stdClass(), $this->constraint());

        $this->assertNoViolation();
    }

    public function testTwoKeysOfTheSameSetAreRefusedAtTheSecondOne(): void
    {
        $this->sendBody('{"host_group_ids": [1], "host_group_ids_to_add": [2]}');
        $this->setPropertyPath('');

        $constraint = $this->constraint();
        $this->validator->validate(new \stdClass(), $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ keys }}', 'host_group_ids, host_group_ids_to_add, host_group_ids_to_remove')
            ->atPath('hostGroupIdsToAdd')
            ->assertRaised();
    }

    public function testKeysOfDifferentSetsAreFine(): void
    {
        $this->sendBody('{"host_group_ids": [1], "category_ids_to_add": [2]}');
        $this->setPropertyPath('');

        $this->validator->validate(new \stdClass(), $this->constraint());

        $this->assertNoViolation();
    }

    public function testTheKeysAreLookedUpInsideTheSubObjectTheConstraintIsOn(): void
    {
        $this->sendBody('{"host_group_ids": [1], "host_group_ids_to_add": [2], "notifications": {"contacts": [1]}}');
        $this->setPropertyPath('notifications');

        $this->validator->validate(new \stdClass(), new ExclusiveKeys([['contacts', 'contactsToAdd']]));

        $this->assertNoViolation();
    }

    public function testWithoutARequestNothingIsChecked(): void
    {
        $this->setPropertyPath('');

        $this->validator->validate(new \stdClass(), $this->constraint());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExclusiveKeysValidator(new CurrentRequestPayload($this->requestStack));
    }

    private function constraint(): ExclusiveKeys
    {
        return new ExclusiveKeys([
            ['hostGroupIds', 'hostGroupIdsToAdd', 'hostGroupIdsToRemove'],
            ['categoryIds', 'categoryIdsToAdd', 'categoryIdsToRemove'],
        ]);
    }

    private function sendBody(string $json): void
    {
        $this->requestStack->push(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json']));
    }
}
