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

namespace Tests\EventSubscriber;

use Centreon\Application\ApiPlatform;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Domain\RequestParameters\RequestParametersException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use EventSubscriber\CentreonEventSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\EventSubscriber\Double\FakeHttpKernel;

final class CentreonEventSubscriberTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideMalformedPaginationParameters(): \Generator
    {
        yield 'non-numeric page' => ['page=abc', RequestParameters::NAME_FOR_PAGE];

        yield 'empty page' => ['page=', RequestParameters::NAME_FOR_PAGE];

        yield 'decimal page' => ['page=1.5', RequestParameters::NAME_FOR_PAGE];

        yield 'array-shaped page' => ['page[]=1', RequestParameters::NAME_FOR_PAGE];

        yield 'page beyond PHP_INT_MAX' => ['page=99999999999999999999', RequestParameters::NAME_FOR_PAGE];

        yield 'non-numeric limit' => ['limit=abc', RequestParameters::NAME_FOR_LIMIT];

        yield 'empty limit' => ['limit=', RequestParameters::NAME_FOR_LIMIT];

        yield 'array-shaped limit' => ['limit[]=10', RequestParameters::NAME_FOR_LIMIT];
    }

    #[DataProvider('provideMalformedPaginationParameters')]
    public function testInitRequestParametersRejectsNonIntegerPagination(
        string $queryString,
        string $rejectedParameter,
    ): void {
        try {
            $this->createSubscriber(new RequestParameters())
                ->initRequestParameters($this->createRequestEvent($queryString));
            self::fail('A BadRequestHttpException was expected.');
        } catch (BadRequestHttpException $exception) {
            self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
            self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getCode());
            self::assertSame(
                RequestParametersException::integer($rejectedParameter)->getMessage(),
                $exception->getMessage()
            );
            self::assertInstanceOf(RequestParametersException::class, $exception->getPrevious());
        }
    }

    public function testInitRequestParametersAcceptsIntegerPageAndLimit(): void
    {
        $requestParameters = new RequestParameters();

        $this->createSubscriber($requestParameters)
            ->initRequestParameters($this->createRequestEvent('page=3&limit=50'));

        self::assertSame(3, $requestParameters->getPage());
        self::assertSame(50, $requestParameters->getLimit());
    }

    public function testInitRequestParametersFallsBackToTheDefaultPagination(): void
    {
        $requestParameters = new RequestParameters();

        $this->createSubscriber($requestParameters)
            ->initRequestParameters($this->createRequestEvent(''));

        self::assertSame(RequestParameters::DEFAULT_PAGE, $requestParameters->getPage());
        self::assertSame(RequestParameters::DEFAULT_LIMIT, $requestParameters->getLimit());
    }

    private function createSubscriber(RequestParameters $requestParameters): CentreonEventSubscriber
    {
        return new CentreonEventSubscriber(
            $requestParameters,
            new Security(new ServiceLocator([])),
            new ApiPlatform(),
            new Contact(),
            new ExceptionLogger(new NullLogger()),
            '26.11',
            'Api-Version',
            _CENTREON_PATH_ . 'www/locale',
        );
    }

    private function createRequestEvent(string $queryString): RequestEvent
    {
        return new RequestEvent(
            new FakeHttpKernel(),
            Request::create('/api/latest/monitoring/resources?' . $queryString),
            HttpKernelInterface::MAIN_REQUEST
        );
    }
}
