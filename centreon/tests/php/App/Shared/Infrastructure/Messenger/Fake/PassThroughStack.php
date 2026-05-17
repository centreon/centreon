<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\Shared\Infrastructure\Messenger\Fake;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class PassThroughStack implements StackInterface
{
    public function __construct(private Envelope $envelope)
    {
    }

    public function next(): MiddlewareInterface
    {
        return new class ($this->envelope) implements MiddlewareInterface {
            public function __construct(private readonly Envelope $envelope)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope;
            }
        };
    }
}
