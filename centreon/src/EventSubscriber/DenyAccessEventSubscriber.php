<?php

namespace EventSubscriber;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RouterInterface;

class DenyAccessEventSubscriber implements EventSubscriberInterface
{

    public function __construct(private readonly ContactInterface $contact, private readonly RouterInterface $router)
    {
    }

    public function denyAccess(ControllerEvent $event)
    {
        dump($event);
        $controller = $event->getController();
        dump($controller);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [ KernelEvents::CONTROLLER  => 'denyAccess'];
    }
}