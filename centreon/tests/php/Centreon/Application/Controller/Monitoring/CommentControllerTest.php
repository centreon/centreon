<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Tests\Centreon\Application\Controller\Monitoring;

use Centreon\Application\Controller\Monitoring\CommentController;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Monitoring\Comment\CommentService;
use Centreon\Domain\Monitoring\MonitoringService;
use Centreon\Domain\Monitoring\Resource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use FOS\RestBundle\View\View;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CommentControllerTest extends TestCase
{
    private const DECODING_ERROR_MESSAGE = 'Error when decoding your sent data';

    private Contact $adminContact;
    private Resource $hostResource;
    private Resource $serviceResource;
    private string $correctJsonComment;
    private string $wrongJsonComment;
    private string $hostCommentJson;
    private string $serviceCommentJson;
    private CommentService&MockObject $commentService;
    private MonitoringService $monitoringService;
    private ContainerInterface $container;
    private Request&MockObject $request;
    private ReadAccessGroupRepositoryInterface $readAccessGroupRepository;

    protected function setUp(): void
    {
        $timezone = new \DateTimeZone('Europe/Paris');
        $dateTime = new \DateTime('now');
        $date = $dateTime->format(\DateTime::ATOM);

        $this->adminContact = (new Contact())
            ->setId(1)
            ->setName('admin')
            ->setAdmin(true)
            ->setTimezone($timezone);

        $correctJsonComment = [
            'resources' => [
                [
                    'type' => 'host',
                    'id' => 1,
                    'parent' => null,
                    'comment' => 'simple comment on a host resource',
                    'date' => null
                ],
                [
                    'type' => 'service',
                    'id' => 1,
                    'parent' => [
                        'id' => 1,
                    ],
                    'comment' => 'simple comment on a service resource',
                    'date' => $date
                ],
            ],
        ];

        $hostCommentJson = [
            'comment' => 'single comment on a service',
            'date' => $date
        ];

        $serviceCommentJson = [
            'comment' => 'single comment on a host',
            'date' => $date
        ];

        $this->hostResource = (new Resource())
            ->setType($correctJsonComment['resources'][0]['type'])
            ->setId($correctJsonComment['resources'][0]['id']);
        $this->serviceResource = (new Resource())
            ->setType($correctJsonComment['resources'][1]['type'])
            ->setId($correctJsonComment['resources'][1]['id'])
            ->setParent($this->hostResource);

        $this->correctJsonComment = json_encode($correctJsonComment);
        $this->serviceCommentJson = json_encode($serviceCommentJson);
        $this->hostCommentJson = json_encode($hostCommentJson);

        $this->wrongJsonComment = json_encode([
            'unknown_property' => 'unknown',
        ]);

        $this->commentService = $this->createMock(CommentService::class);
        $this->monitoringService = $this->createMock(MonitoringService::class);
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);

        $this->readAccessGroupRepository->method('findByContact')->willReturn([]);
        $this->readAccessGroupRepository->method('hasAccessToResources')->willReturn(true);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($this->adminContact);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->any())
            ->method('getToken')
            ->willReturn($token);

        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $this->container->expects($this->any())
            ->method('get')
            ->withConsecutive(
                [$this->equalTo('security.authorization_checker')],
                [$this->equalTo('security.token_storage')],
                [$this->equalTo('parameter_bag')]
            )
            ->willReturnOnConsecutiveCalls(
                $authorizationChecker,
                $tokenStorage,
                new class () {
                    public function get()
                    {
                        return __DIR__ . '/../../../../../';
                    }
                }
            );

        $this->request = $this->createMock(Request::class);
    }

    /**
     * Testing wrongly formatted JSON POST data for addResourcesComment
     */
    public function testaddResourcesCommentBadJsonFormat(): void
    {
        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn('[}');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::DECODING_ERROR_MESSAGE);
        $commentController->addResourcesComment($this->request);
    }

    /**
     * Testing with wrong property added to the POST JSON for addResourcesComment
     */
    public function testCommentResourcesBadJsonProperties(): void
    {
        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->any())
            ->method('getContent')
            ->willReturn($this->wrongJsonComment);
        $this->expectException(\InvalidArgumentException::class);
        $commentController->addResourcesComment($this->request);
    }

    /**
     * Testing with a correct JSON POST data and successfully adding a comment to a resource
     */
    public function testAddResourcesCommentSuccess(): void
    {
        $this->commentService->expects($this->any())
            ->method('filterByContact')
            ->willReturn($this->commentService);

        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->any())
            ->method('getContent')
            ->willReturn($this->correctJsonComment);
        $view = $commentController->addResourcesComment($this->request);

        $this->assertEquals($view, View::create(null, Response::HTTP_NO_CONTENT));
    }

    /**
     * Testing with wrongly formatted JSON POST data for addHostComment
     */
    public function testAddHostCommentBadJsonFormat(): void
    {
        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn('[}');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::DECODING_ERROR_MESSAGE);
        $commentController->addHostComment($this->request, $this->hostResource->getId());
    }
    /**
     * Testing with wrong property added to the POST JSON for addHostComment
     */
    public function testAddHostCommentBadJsonProperties(): void
    {
        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->any())
            ->method('getContent')
            ->willReturn($this->wrongJsonComment);
        $this->expectException(\InvalidArgumentException::class);
        $commentController->addHostComment($this->request, $this->hostResource->getId());
    }
    /**
     * Testing with a correct JSON POST data and successfully adding a comment for a host resource
     */
    public function testAddHostCommentSuccess(): void
    {
        $this->commentService->expects($this->any())
            ->method('filterByContact')
            ->willReturn($this->commentService);

        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->any())
            ->method('getContent')
            ->willReturn($this->hostCommentJson);

        $view = $commentController->addHostComment($this->request, $this->hostResource->getId());

        $this->assertEquals($view, View::create(null, Response::HTTP_NO_CONTENT));
    }

    /**
     * Testing with wrongly formatted JSON POST data for addServiceComment
     */
    public function testAddServiceCommentBadJsonFormat(): void
    {
        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn('[}');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::DECODING_ERROR_MESSAGE);
        $commentController->addServiceComment(
            $this->request,
            $this->serviceResource->getParent()->getId(),
            $this->serviceResource->getId()
        );
    }
    /**
     * Testing with wrong property added to the POST JSON for addServiceComment
     */
    public function testAddServiceCommentBadJsonProperties(): void
    {
        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->any())
            ->method('getContent')
            ->willReturn($this->wrongJsonComment);
        $this->expectException(\InvalidArgumentException::class);
        $commentController->addServiceComment(
            $this->request,
            $this->serviceResource->getParent()->getId(),
            $this->serviceResource->getId()
        );
    }
    /**
     * Testing with a correct JSON POST data and successfully adding comment for a service resource
     */
    public function testAddServiceCommentSuccess(): void
    {
        $this->commentService->expects($this->any())
        ->method('filterByContact')
        ->willReturn($this->commentService);

        $commentController = new CommentController(
            $this->commentService,
            $this->monitoringService,
            $this->readAccessGroupRepository
        );
        $commentController->setContainer($this->container);

        $this->request->expects($this->any())
            ->method('getContent')
            ->willReturn($this->serviceCommentJson);

        $view = $commentController->addServiceComment(
            $this->request,
            $this->serviceResource->getParent()->getId(),
            $this->serviceResource->getId()
        );

        $this->assertEquals($view, View::create(null, Response::HTTP_NO_CONTENT));
    }
}
