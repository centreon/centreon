<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Contact\Application\UseCase\FindContactTemplates;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Contact\Application\Exception\ContactTemplateException;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Contact\Application\UseCase\FindContactTemplates\FindContactTemplates;
use Core\Contact\Application\UseCase\FindContactTemplates\FindContactTemplatesResponse;
use Core\Contact\Domain\Model\ContactTemplate;

beforeEach(function (): void {
    $this->repository = $this->createMock(ReadContactTemplateRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->repositoryException = $this->createMock(RepositoryException::class);
});

it('should present an ErrorResponse when an exception occurred', function (): void {
    $useCase = new FindContactTemplates($this->repository,$this->user);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->repository
        ->expects($this->once())
        ->method('findAll')
        ->willThrowException($this->repositoryException);

    $presenter = new FindContactTemplatesPresenterStub($this->presenterFormatter);
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())->toBe(
            ContactTemplateException::errorWhileSearchingForContactTemplate()->getMessage()
        );
});

it('should present a ForbiddenResponse if the user does not have the read menu access to contact templates', function (): void {
    $useCase = new FindContactTemplates($this->repository,$this->user);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $presenter = new FindContactTemplatesPresenterStub($this->presenterFormatter);
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->data->getMessage())->toBe(
            ContactTemplateException::listingNotAllowed()->getMessage()
        );
});

it('should present a FindContactTemplatesResponse when no error occured', function (): void {
    $useCase = new FindContactTemplates($this->repository,$this->user);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $contactTemplate = new ContactTemplate(1, 'contact_template');
    $this->repository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([$contactTemplate]);

    $presenter = new FindContactTemplatesPresenterStub($this->presenterFormatter);
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(FindContactTemplatesResponse::class)
        ->and($presenter->data->contactTemplates[0])->toBe(
            [
                'id' => 1,
                'name' => 'contact_template'
            ]
        );
});
