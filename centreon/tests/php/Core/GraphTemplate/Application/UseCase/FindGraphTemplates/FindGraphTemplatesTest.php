<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\GraphTemplate\Application\UseCase\FindGraphTemplates;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\GraphTemplate\Application\Exception\GraphTemplateException;
use Core\GraphTemplate\Application\Repository\ReadGraphTemplateRepositoryInterface;
use Core\GraphTemplate\Application\UseCase\FindGraphTemplates\FindGraphTemplates;
use Core\GraphTemplate\Application\UseCase\FindGraphTemplates\FindGraphTemplatesResponse;
use Core\GraphTemplate\Domain\Model\GraphTemplate;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Tests\Core\GraphTemplate\Infrastructure\API\FindGraphTemplates\FindGraphTemplatesPresenterStub;

beforeEach(closure: function (): void {
    $this->readGraphTemplateRepository = $this->createMock(ReadGraphTemplateRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);
    $this->presenter = new FindGraphTemplatesPresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new FindGraphTemplates(
        $this->createMock(RequestParametersInterface::class),
        $this->readGraphTemplateRepository,
        $this->contact
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->contact
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(GraphTemplateException::accessNotAllowed()->getMessage());
});

it(
    'should present an ErrorResponse when an exception of type RequestParametersTranslatorException is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $exception = new RequestParametersTranslatorException('Error');

        $this->readGraphTemplateRepository
            ->expects($this->once())
            ->method('findByRequestParameters')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe($exception->getMessage());
    }
);

it(
    'should present an ErrorResponse when an exception of type Exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $exception = new \Exception('Error');

        $this->readGraphTemplateRepository
            ->expects($this->once())
            ->method('findByRequestParameters')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe(GraphTemplateException::errorWhileSearching($exception)->getMessage());
    }
);

it(
    'should present a FindGraphTemplatesResponse when everything has gone well',
    function (): void {
        $graphTemplate = new GraphTemplate(
            id: 1,
            name: 'graph template name',
            verticalAxisLabel: 'vertical axis label',
            width: 150,
            height: 250,
            base: 1000,
            gridLowerLimit: 0,
            gridUpperLimit: 115,
            isUpperLimitSizedToMax: false,
            isGraphScaled: true,
            isDefaultCentreonTemplate: true,
        );

        $this->contact
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readGraphTemplateRepository
            ->expects($this->once())
            ->method('findByRequestParameters')
            ->willReturn([$graphTemplate]);

        ($this->useCase)($this->presenter);

        $graphTemplatesResponse = $this->presenter->response;
        expect($graphTemplatesResponse)->toBeInstanceOf(FindGraphTemplatesResponse::class);
        expect($graphTemplatesResponse->graphTemplates[0]->id)->toBe($graphTemplate->getId());
        expect($graphTemplatesResponse->graphTemplates[0]->name)->toBe($graphTemplate->getName());
        expect($graphTemplatesResponse->graphTemplates[0]->verticalAxisLabel)->toBe('vertical axis label');
        expect($graphTemplatesResponse->graphTemplates[0]->width)->toBe(150);
        expect($graphTemplatesResponse->graphTemplates[0]->height)->toBe(250);
        expect($graphTemplatesResponse->graphTemplates[0]->base)->toBe(1000);
        expect($graphTemplatesResponse->graphTemplates[0]->gridLowerLimit)->toBe(0.0);
        expect($graphTemplatesResponse->graphTemplates[0]->gridUpperLimit)->toBe(115.0);
        expect($graphTemplatesResponse->graphTemplates[0]->isUpperLimitSizedToMax)->toBe(false);
        expect($graphTemplatesResponse->graphTemplates[0]->isGraphScaled)->toBe(true);
        expect($graphTemplatesResponse->graphTemplates[0]->isDefaultCentreonTemplate)->toBe(true);
    }
);
