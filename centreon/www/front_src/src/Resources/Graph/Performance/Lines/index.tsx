<<<<<<< HEAD
=======
import * as React from 'react';

>>>>>>> centreon/dev-21.10.x
import { difference, min, max, isNil } from 'ramda';
import { Scale } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';

<<<<<<< HEAD
import { alpha } from '@mui/material';
=======
import { alpha } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { Line, TimeValue } from '../models';
import {
  getUnits,
  getSortedStackedLines,
  getTimeSeriesForLines,
  getMin,
  getMax,
  getNotInvertedStackedLines,
  getInvertedStackedLines,
  getYScale,
} from '../timeSeries';

import RegularLine from './RegularLine';
import RegularAnchorPoint from './AnchorPoint/RegularAnchorPoint';
import StackedLines from './StackedLines';

interface Props {
<<<<<<< HEAD
  displayTimeValues: boolean;
=======
>>>>>>> centreon/dev-21.10.x
  graphHeight: number;
  leftScale: ScaleLinear<number, number>;
  lines: Array<Line>;
  rightScale: ScaleLinear<number, number>;
  timeSeries: Array<TimeValue>;
  timeTick: Date | null;
  xScale: ScaleTime<number, number>;
}

interface YScales {
  leftScale: ScaleLinear<number, number>;
  rightScale: ScaleLinear<number, number>;
}

const getStackedYScale = ({
  leftScale,
  rightScale,
}: YScales): ScaleLinear<number, number> => {
  const minDomain = min(
    getMin(leftScale.domain()),
    getMin(rightScale.domain()),
  );
  const maxDomain = max(
    getMax(leftScale.domain()),
    getMax(rightScale.domain()),
  );

  const minRange = min(getMin(leftScale.range()), getMin(rightScale.range()));
  const maxRange = max(getMax(leftScale.range()), getMax(rightScale.range()));

  return Scale.scaleLinear<number>({
    domain: [minDomain, maxDomain],
    nice: true,
    range: [maxRange, minRange],
  });
};

interface FillColor {
  areaColor: string;
  transparency: number;
}

export const getFillColor = ({
  transparency,
  areaColor,
}: FillColor): string | undefined =>
  transparency ? alpha(areaColor, 1 - transparency * 0.01) : undefined;

const Lines = ({
  xScale,
  leftScale,
  rightScale,
  timeSeries,
  lines,
  graphHeight,
  timeTick,
<<<<<<< HEAD
  displayTimeValues,
=======
>>>>>>> centreon/dev-21.10.x
}: Props): JSX.Element => {
  const [, secondUnit, thirdUnit] = getUnits(lines);

  const stackedLines = getSortedStackedLines(lines);

  const regularStackedLines = getNotInvertedStackedLines(lines);
  const regularStackedTimeSeries = getTimeSeriesForLines({
    lines: regularStackedLines,
    timeSeries,
  });

  const invertedStackedLines = getInvertedStackedLines(lines);
  const invertedStackedTimeSeries = getTimeSeriesForLines({
    lines: invertedStackedLines,
    timeSeries,
  });

  const stackedYScale = getStackedYScale({ leftScale, rightScale });

  const regularLines = difference(lines, stackedLines);

  return (
<<<<<<< HEAD
    <g>
      <StackedLines
        displayTimeValues={displayTimeValues}
=======
    <>
      <StackedLines
>>>>>>> centreon/dev-21.10.x
        lines={regularStackedLines}
        timeSeries={regularStackedTimeSeries}
        timeTick={timeTick}
        xScale={xScale}
        yScale={stackedYScale}
      />
      <StackedLines
<<<<<<< HEAD
        displayTimeValues={displayTimeValues}
=======
>>>>>>> centreon/dev-21.10.x
        lines={invertedStackedLines}
        timeSeries={invertedStackedTimeSeries}
        timeTick={timeTick}
        xScale={xScale}
        yScale={stackedYScale}
      />
<<<<<<< HEAD
      <g>
=======
      <>
>>>>>>> centreon/dev-21.10.x
        {regularLines.map(
          ({
            metric,
            areaColor,
            transparency,
            lineColor,
            filled,
            unit,
            highlight,
            invert,
          }) => {
            const yScale = getYScale({
              hasMoreThanTwoUnits: !isNil(thirdUnit),
              invert,
              leftScale,
              rightScale,
              secondUnit,
              unit,
            });

            return (
<<<<<<< HEAD
              <g key={metric}>
                <RegularAnchorPoint
                  areaColor={areaColor}
                  displayTimeValues={displayTimeValues}
=======
              <React.Fragment key={metric}>
                <RegularAnchorPoint
                  areaColor={areaColor}
>>>>>>> centreon/dev-21.10.x
                  lineColor={lineColor}
                  metric={metric}
                  timeSeries={timeSeries}
                  timeTick={timeTick}
                  transparency={transparency}
                  xScale={xScale}
                  yScale={yScale}
                />
                <RegularLine
                  areaColor={areaColor}
                  filled={filled}
                  graphHeight={graphHeight}
                  highlight={highlight}
                  lineColor={lineColor}
                  metric={metric}
                  timeSeries={timeSeries}
                  transparency={transparency}
                  unit={unit}
                  xScale={xScale}
                  yScale={yScale}
                />
<<<<<<< HEAD
              </g>
            );
          },
        )}
      </g>
    </g>
=======
              </React.Fragment>
            );
          },
        )}
      </>
    </>
>>>>>>> centreon/dev-21.10.x
  );
};

export default Lines;
