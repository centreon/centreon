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

namespace Core\Metric\Domain\TimeSeries;

use Core\Metric\Domain\Exception\RpnExpressionException;

/**
 * Expression of a virtual metric, in the RPN of rrdtool (rrdgraph_rpn), evaluated in PHP on series
 * of the same time grid. Unknown values are NAN and follow the rrdtool rules: they propagate through
 * arithmetic and comparisons, IF takes them as false, ADDNAN, MINNAN, MAXNAN and AVG skip them.
 *
 * Supported in CDEF: numbers, curves, + - * / % ADDNAN POW ATAN2, LT LE GT GE EQ NE, UN ISINF IF,
 * MIN MAX MINNAN MAXNAN LIMIT AVG, ABS SIN COS LOG EXP SQRT ATAN FLOOR CEIL DEG2RAD RAD2DEG,
 * UNKN INF NEGINF PI, DUP POP EXC DEPTH, TIME NOW STEPWIDTH COUNT PREV.
 * Supported in VDEF: "<curve>,<function>" and "<curve>,<percentile>,PERCENT|PERCENTNAN" with the
 * functions of Consolidation. Any other rrdtool operator (TREND, PREDICT, SORT, LTIME, NEWDAY...)
 * is refused.
 */
final class RpnExpression
{
    private const BINARY = [
        '+', '-', '*', '/', '%', 'ADDNAN', 'POW', 'ATAN2',
        'LT', 'LE', 'GT', 'GE', 'EQ', 'NE', 'MIN', 'MAX', 'MINNAN', 'MAXNAN',
    ];
    private const UNARY = [
        'ABS', 'SIN', 'COS', 'LOG', 'EXP', 'SQRT', 'ATAN', 'FLOOR', 'CEIL', 'DEG2RAD', 'RAD2DEG', 'UN', 'ISINF',
    ];
    private const CONSTANTS = ['UNKN', 'INF', 'NEGINF', 'PI', 'TIME', 'NOW', 'STEPWIDTH', 'COUNT', 'PREV'];
    private const OTHERS = ['IF', 'LIMIT', 'AVG', 'DUP', 'POP', 'EXC', 'DEPTH'];

    /** @var list<string> */
    private readonly array $tokens;

    /**
     * @param string $expression comma separated tokens, the curves named as in the series given to evaluate()
     */
    public function __construct(private readonly string $expression)
    {
        $this->tokens = array_map('trim', explode(',', $expression));
    }

    /**
     * Value of the CDEF expression at each timestamp of the grid.
     *
     * @param array<string, list<float>> $series curves by name, one value per timestamp of $grid
     * @param TimeGrid $grid
     * @param int|null $now value of NOW (current time by default)
     *
     * @throws RpnExpressionException
     *
     * @return list<float>
     */
    public function evaluate(array $series, TimeGrid $grid, ?int $now = null): array
    {
        $this->validate($series);
        $now ??= time();
        $result = [];
        $previous = NAN;
        foreach ($grid->getTimestamps() as $index => $timestamp) {
            $previous = $this->evaluateRow($series, $index, $timestamp, $grid->getStep(), $now, $previous);
            $result[] = $previous;
        }

        return $result;
    }

    /**
     * Value of the VDEF expression over the whole series.
     *
     * @param array<string, list<float>> $series
     * @param int $step
     *
     * @throws RpnExpressionException
     */
    public function evaluateVdef(array $series, int $step): float
    {
        $count = count($this->tokens);
        $function = mb_strtoupper($this->tokens[$count - 1]);
        if (($count !== 2 && $count !== 3) || ! array_key_exists($this->tokens[0], $series)) {
            throw RpnExpressionException::invalidVdef($this->expression);
        }
        if (! in_array($function, Consolidation::FUNCTIONS, true)) {
            throw RpnExpressionException::unsupportedToken($this->tokens[$count - 1], $this->expression);
        }
        $isPercent = $function === 'PERCENT' || $function === 'PERCENTNAN';
        if ($isPercent !== ($count === 3) || ($isPercent && ! is_numeric($this->tokens[1]))) {
            throw RpnExpressionException::invalidVdef($this->expression);
        }

        return Consolidation::compute(
            $function,
            $series[$this->tokens[0]],
            $step,
            $isPercent ? (float) $this->tokens[1] : null
        );
    }

    /**
     * Checks the syntax of the expression without data: it can be computed from the given curves.
     *
     * @param list<string> $curveNames curves the expression may read
     * @param bool $isVdef VDEF (one value) rather than CDEF (one value per timestamp)
     *
     * @throws RpnExpressionException
     */
    public function check(array $curveNames, bool $isVdef): void
    {
        $grid = TimeGrid::forPeriod(0, 600, 60);
        $series = array_fill_keys($curveNames, array_fill(0, $grid->count(), 1.0));
        if ($isVdef) {
            $this->evaluateVdef($series, $grid->getStep());
        } else {
            $this->evaluate($series, $grid, 0);
        }
    }

    /**
     * @return list<string> curves the expression reads
     */
    public function getCurveNames(): array
    {
        return array_values(array_filter(
            $this->tokens,
            fn (string $token): bool => ! is_numeric($token) && ! $this->isOperator($token)
        ));
    }

    /**
     * @param array<string, list<float>> $series
     *
     * @throws RpnExpressionException
     */
    private function validate(array $series): void
    {
        foreach ($this->tokens as $token) {
            if (is_numeric($token) || $this->isOperator($token) || array_key_exists($token, $series)) {
                continue;
            }
            if (preg_match('/^[A-Z][A-Z0-9]*$/', $token) === 1) {
                throw RpnExpressionException::unsupportedToken($token, $this->expression);
            }

            throw RpnExpressionException::unknownCurve($token, $this->expression);
        }
    }

    private function isOperator(string $token): bool
    {
        return in_array($token, self::BINARY, true)
            || in_array($token, self::UNARY, true)
            || in_array($token, self::CONSTANTS, true)
            || in_array($token, self::OTHERS, true);
    }

    /**
     * @param array<string, list<float>> $series
     *
     * @throws RpnExpressionException
     */
    private function evaluateRow(array $series, int $index, int $timestamp, int $step, int $now, float $previous): float
    {
        /** @var list<float> $stack */
        $stack = [];
        foreach ($this->tokens as $token) {
            if (array_key_exists($token, $series)) {
                $stack[] = (float) ($series[$token][$index] ?? NAN);
            } elseif (is_numeric($token)) {
                $stack[] = (float) $token;
            } elseif (in_array($token, self::BINARY, true)) {
                $right = $this->pop($stack, $token);
                $left = $this->pop($stack, $token);
                $stack[] = self::binary($token, $left, $right);
            } elseif (in_array($token, self::UNARY, true)) {
                $stack[] = self::unary($token, $this->pop($stack, $token));
            } else {
                $stack = $this->other($token, $stack, $index, $timestamp, $step, $now, $previous);
            }
        }
        if (count($stack) !== 1) {
            throw RpnExpressionException::invalidResult($this->expression);
        }

        return $stack[0];
    }

    /**
     * @param list<float> $stack
     *
     * @throws RpnExpressionException
     */
    private function pop(array &$stack, string $token): float
    {
        if ($stack === []) {
            throw RpnExpressionException::stackUnderflow($token, $this->expression);
        }

        return array_pop($stack);
    }

    private static function binary(string $operator, float $left, float $right): float
    {
        $isUnknown = is_nan($left) || is_nan($right);

        return match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => fdiv($left, $right),
            '%' => fmod($left, $right),
            'POW' => $left ** $right,
            'ATAN2' => atan2($left, $right),
            'ADDNAN' => is_nan($left) ? $right : (is_nan($right) ? $left : $left + $right),
            'LT' => $isUnknown ? NAN : (float) ($left < $right),
            'LE' => $isUnknown ? NAN : (float) ($left <= $right),
            'GT' => $isUnknown ? NAN : (float) ($left > $right),
            'GE' => $isUnknown ? NAN : (float) ($left >= $right),
            'EQ' => $isUnknown ? NAN : (float) ($left === $right),
            'NE' => $isUnknown ? NAN : (float) ($left !== $right),
            'MIN' => $isUnknown ? NAN : min($left, $right),
            'MAX' => $isUnknown ? NAN : max($left, $right),
            'MINNAN' => is_nan($left) ? $right : (is_nan($right) ? $left : min($left, $right)),
            'MAXNAN' => is_nan($left) ? $right : (is_nan($right) ? $left : max($left, $right)),
            default => NAN,
        };
    }

    private static function unary(string $operator, float $operand): float
    {
        return match ($operator) {
            'ABS' => abs($operand),
            'SIN' => sin($operand),
            'COS' => cos($operand),
            'LOG' => log($operand),
            'EXP' => exp($operand),
            'SQRT' => sqrt($operand),
            'ATAN' => atan($operand),
            'FLOOR' => floor($operand),
            'CEIL' => ceil($operand),
            'DEG2RAD' => deg2rad($operand),
            'RAD2DEG' => rad2deg($operand),
            'UN' => (float) is_nan($operand),
            'ISINF' => (float) is_infinite($operand),
            default => NAN,
        };
    }

    /**
     * @param list<float> $stack
     *
     * @throws RpnExpressionException
     *
     * @return list<float>
     */
    private function other(
        string $token,
        array $stack,
        int $index,
        int $timestamp,
        int $step,
        int $now,
        float $previous,
    ): array {
        switch ($token) {
            case 'UNKN':
                $stack[] = NAN;
                break;
            case 'INF':
                $stack[] = INF;
                break;
            case 'NEGINF':
                $stack[] = -INF;
                break;
            case 'PI':
                $stack[] = M_PI;
                break;
            case 'TIME':
                $stack[] = (float) $timestamp;
                break;
            case 'NOW':
                $stack[] = (float) $now;
                break;
            case 'STEPWIDTH':
                $stack[] = (float) $step;
                break;
            case 'COUNT':
                $stack[] = (float) ($index + 1);
                break;
            case 'PREV':
                $stack[] = $previous;
                break;
            case 'DUP':
                $value = $this->pop($stack, $token);
                array_push($stack, $value, $value);
                break;
            case 'POP':
                $this->pop($stack, $token);
                break;
            case 'EXC':
                $right = $this->pop($stack, $token);
                $left = $this->pop($stack, $token);
                array_push($stack, $right, $left);
                break;
            case 'DEPTH':
                $stack[] = (float) count($stack);
                break;
            case 'IF':
                $else = $this->pop($stack, $token);
                $then = $this->pop($stack, $token);
                $condition = $this->pop($stack, $token);
                $stack[] = (is_nan($condition) || $condition === 0.0) ? $else : $then;
                break;
            case 'LIMIT':
                $upper = $this->pop($stack, $token);
                $lower = $this->pop($stack, $token);
                $value = $this->pop($stack, $token);
                $stack[] = (is_nan($value) || is_nan($lower) || is_nan($upper)
                    || $value < min($lower, $upper) || $value > max($lower, $upper)) ? NAN : $value;
                break;
            case 'AVG':
                $count = $this->pop($stack, $token);
                if (is_nan($count) || $count < 0 || (int) $count > count($stack)) {
                    throw RpnExpressionException::stackUnderflow($token, $this->expression);
                }
                $values = $count >= 1 ? array_splice($stack, -(int) $count) : [];
                $known = array_filter($values, static fn (float $value): bool => ! is_nan($value));
                $stack[] = $known === [] ? NAN : array_sum($known) / count($known);
                break;
            default:
                throw RpnExpressionException::unsupportedToken($token, $this->expression);
        }

        return array_values($stack);
    }
}
