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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use App\MonitoringConfiguration\Application\Command\DuplicateHostTemplateCommand;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\DuplicateHostTemplateInput;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Webmozart\Assert\Assert;

/**
 * One copy per call, answered with 204.
 *
 * The body is optional, which the platform deserializer refuses with a 415: the operation does not
 * deserialize, and the body is read here when there is one.
 *
 * @implements ProcessorInterface<null, null>
 */
final readonly class DuplicateHostTemplateProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private Security $security,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        Assert::keyExists($uriVariables, 'id');
        Assert::integer($uriVariables['id']);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $input = $this->readInput($context['request'] ?? null);
        $name = $input?->name !== null ? HostTemplateName::fromUserInput($input->name) : null;

        $this->commandBus->execute(new DuplicateHostTemplateCommand(
            id: new HostTemplateId($uriVariables['id']),
            name: $name,
            actor: $credentialUser->credential->actingUser(),
        ));

        return null;
    }

    private function readInput(mixed $request): ?DuplicateHostTemplateInput
    {
        Assert::isInstanceOf($request, Request::class);
        if (trim($request->getContent()) === '') {
            return null;
        }

        try {
            $body = json_decode($request->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('The request body is not valid JSON.', $exception);
        }

        if (! $body instanceof \stdClass) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        try {
            $input = $this->denormalizer->denormalize(
                json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR),
                DuplicateHostTemplateInput::class,
                'json',
                [DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true],
            );
        } catch (PartialDenormalizationException $exception) {
            $violations = new ConstraintViolationList();
            foreach ($exception->getErrors() as $error) {
                if ($error instanceof NotNormalizableValueException) {
                    $violations->add(new ConstraintViolation(
                        sprintf('This value should be of type %s.', implode('|', $error->getExpectedTypes() ?? ['unknown'])),
                        null,
                        [],
                        null,
                        $error->getPath(),
                        null,
                    ));
                }
            }

            throw new ValidationException($violations, previous: $exception);
        }

        Assert::isInstanceOf($input, DuplicateHostTemplateInput::class);
        $this->validator->validate($input);

        return $input;
    }
}
