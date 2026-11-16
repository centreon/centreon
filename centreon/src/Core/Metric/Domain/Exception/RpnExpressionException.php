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

namespace Core\Metric\Domain\Exception;

/**
 * A virtual metric expression that cannot be computed outside rrdtool.
 */
final class RpnExpressionException extends \Exception
{
    public static function unsupportedToken(string $token, string $expression): self
    {
        return new self(sprintf(
            'Virtual metric expression "%s": "%s" is not supported when the graphs are read from VictoriaMetrics',
            $expression,
            $token
        ));
    }

    public static function unknownCurve(string $token, string $expression): self
    {
        return new self(sprintf(
            'Virtual metric expression "%s": "%s" is neither a number, an operator nor a metric of the graph',
            $expression,
            $token
        ));
    }

    public static function stackUnderflow(string $token, string $expression): self
    {
        return new self(sprintf(
            'Virtual metric expression "%s": not enough operands for "%s"',
            $expression,
            $token
        ));
    }

    public static function invalidResult(string $expression): self
    {
        return new self(sprintf(
            'Virtual metric expression "%s" does not leave exactly one value',
            $expression
        ));
    }

    public static function invalidVdef(string $expression): self
    {
        return new self(sprintf(
            'Virtual metric expression "%s": a VDEF expression is "<metric>,<function>" or "<metric>,<percent>,PERCENT"',
            $expression
        ));
    }
}
