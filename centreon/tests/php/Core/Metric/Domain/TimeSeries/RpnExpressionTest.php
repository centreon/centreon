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

namespace Tests\Core\Metric\Domain\TimeSeries;

use Core\Metric\Domain\Exception\RpnExpressionException;
use Core\Metric\Domain\TimeSeries\Consolidation;
use Core\Metric\Domain\TimeSeries\RpnExpression;
use Core\Metric\Domain\TimeSeries\TimeGrid;

/**
 * Evaluates $expression on one row where a = $left and b = $right.
 */
function rpnRow(string $expression, float $left = 6.0, float $right = 4.0): float
{
    $grid = TimeGrid::forPeriod(0, 60, 60); // one row at 60

    return (new RpnExpression($expression))->evaluate(['a' => [$left], 'b' => [$right]], $grid, 1000)[0];
}

/**
 * NAN cannot be compared with toBe().
 */
function expectRpnValue(float $actual, float $expected): void
{
    if (is_nan($expected)) {
        expect(is_nan($actual))->toBeTrue();
    } else {
        expect($actual)->toEqualWithDelta($expected, 1e-12);
    }
}

it('should compute each operator as rrdtool', function (string $expression, float $expected): void {
    expectRpnValue(rpnRow($expression), $expected);
})->with([
    'addition' => ['a,b,+', 10.0],
    'subtraction' => ['a,b,-', 2.0],
    'multiplication' => ['a,b,*', 24.0],
    'division' => ['a,b,/', 1.5],
    'modulo' => ['a,b,%', 2.0],
    'power' => ['a,2,POW', 36.0],
    'atan2' => ['b,a,ATAN2', atan2(4.0, 6.0)],
    'addnan' => ['a,b,ADDNAN', 10.0],
    'lower than' => ['a,b,LT', 0.0],
    'lower or equal' => ['b,4,LE', 1.0],
    'greater than' => ['a,b,GT', 1.0],
    'greater or equal' => ['b,5,GE', 0.0],
    'equal' => ['a,6,EQ', 1.0],
    'not equal' => ['a,6,NE', 0.0],
    'minimum' => ['a,b,MIN', 4.0],
    'maximum' => ['a,b,MAX', 6.0],
    'minimum skipping unknown' => ['a,b,MINNAN', 4.0],
    'maximum skipping unknown' => ['a,b,MAXNAN', 6.0],
    'limit inside' => ['a,0,10,LIMIT', 6.0],
    'limit outside' => ['a,0,5,LIMIT', NAN],
    'average' => ['a,b,10,3,AVG', 20.0 / 3],
    'if true' => ['a,b,GT,a,b,IF', 6.0],
    'if false' => ['a,b,LT,a,b,IF', 4.0],
    'absolute' => ['b,a,-,ABS', 2.0],
    'sine' => ['a,SIN', sin(6.0)],
    'cosine' => ['a,COS', cos(6.0)],
    'logarithm' => ['a,LOG', log(6.0)],
    'exponential' => ['a,EXP', exp(6.0)],
    'square root' => ['b,SQRT', 2.0],
    'arc tangent' => ['a,ATAN', atan(6.0)],
    'floor' => ['a,b,/,FLOOR', 1.0],
    'ceil' => ['a,b,/,CEIL', 2.0],
    'degrees to radians' => ['180,DEG2RAD', M_PI],
    'radians to degrees' => ['PI,RAD2DEG', 180.0],
    'is unknown' => ['UNKN,UN', 1.0],
    'is infinite' => ['INF,ISINF', 1.0],
    'negative infinity' => ['NEGINF,a,MAX', 6.0],
    'duplicate' => ['a,DUP,*', 36.0],
    'pop' => ['a,b,POP', 6.0],
    'exchange' => ['a,b,EXC,-', -2.0],
    'depth' => ['a,b,DEPTH,+,+', 12.0],
    'time' => ['TIME', 60.0],
    'now' => ['NOW', 1000.0],
    'step width' => ['STEPWIDTH', 60.0],
    'count' => ['COUNT', 1.0],
    'previous value of the first row' => ['PREV', NAN],
    'scientific number' => ['a,1e3,*', 6000.0],
    'traffic in bits' => ['a,8,*', 48.0],
]);

it('should propagate unknown values as rrdtool', function (string $expression, float $expected): void {
    expectRpnValue(rpnRow($expression, 6.0, NAN), $expected);
})->with([
    'arithmetic' => ['a,b,+', NAN],
    'comparison' => ['a,b,GT', NAN],
    'minimum' => ['a,b,MIN', NAN],
    'addnan' => ['a,b,ADDNAN', 6.0],
    'minnan' => ['a,b,MINNAN', 6.0],
    'maxnan' => ['b,a,MAXNAN', 6.0],
    'if on an unknown condition takes the else branch' => ['b,1,2,IF', 2.0],
    'average skips unknown values' => ['a,b,2,AVG', 6.0],
    'unknown test' => ['b,UN', 1.0],
    'limit' => ['b,0,10,LIMIT', NAN],
]);

it('should follow IEEE 754 on a division by zero', function (string $expression, float $expected): void {
    expectRpnValue(rpnRow($expression, 6.0, 0.0), $expected);
})->with([
    'positive by zero' => ['a,b,/', INF],
    'negative by zero' => ['a,-1,*,b,/', -INF],
    'zero by zero' => ['b,b,/', NAN],
    'modulo zero' => ['a,b,%', NAN],
]);

it('should evaluate every row and PREV on the previous result', function (): void {
    $grid = TimeGrid::forPeriod(0, 180, 60); // rows at 60, 120, 180
    $values = (new RpnExpression('PREV,UN,0,PREV,IF,a,+'))->evaluate(['a' => [1.0, 2.0, 3.0]], $grid);

    expect($values)->toBe([1.0, 3.0, 6.0]);
});

it('should refuse what it does not support', function (string $expression, string $message): void {
    expect(fn () => rpnRow($expression))->toThrow(RpnExpressionException::class, $message);
})->with([
    'trend' => ['a,600,TREND', '"TREND" is not supported'],
    'local time' => ['LTIME', '"LTIME" is not supported'],
    'unknown curve' => ['a,c,+', '"c" is neither a number, an operator nor a metric of the graph'],
    'stack underflow' => ['a,+', 'not enough operands for "+"'],
    'too many values' => ['a,b', 'does not leave exactly one value'],
]);

it('should compute the VDEF functions', function (string $expression, float $expected): void {
    $values = (new RpnExpression($expression))->evaluateVdef(['a' => [2.0, NAN, 4.0, 9.0, INF]], 300);

    expectRpnValue($values, $expected);
})->with([
    'average of the finite values' => ['a,AVERAGE', 5.0],
    'minimum' => ['a,MINIMUM', 2.0],
    'maximum of the finite values' => ['a,MAXIMUM', 9.0],
    'last keeps infinity' => ['a,LAST', INF],
    'first' => ['a,FIRST', 2.0],
    'total' => ['a,TOTAL', 15.0 * 300],
    'standard deviation' => ['a,STDEV', sqrt((4 + 16 + 81) / 3 - 25)],
    'percentile, unknown values first' => ['a,50,PERCENT', 4.0],
    'percentile without unknown values' => ['a,50,PERCENTNAN', 9.0],
]);

it('should refuse an invalid VDEF expression', function (string $expression): void {
    (new RpnExpression($expression))->evaluateVdef(['a' => [1.0]], 60);
})->with(['a,95', 'b,AVERAGE', 'a,AVERAGE,AVERAGE', 'a,x,PERCENT', 'a,TREND'])
    ->throws(RpnExpressionException::class);

it('should give unknown when no value is known', function (string $function): void {
    expect(is_nan(Consolidation::compute($function, [NAN, NAN], 60, 50.0)))->toBeTrue();
})->with(Consolidation::FUNCTIONS);

it('should list the curves an expression reads', function (): void {
    expect((new RpnExpression('v12,vv3,+,8,*'))->getCurveNames())->toBe(['v12', 'vv3']);
});
