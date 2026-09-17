import { getTicks } from '@visx/scale';
import { ScaleLinear } from 'd3-scale';
import { isEmpty } from 'ramda';
import { useMemo } from 'react';

import type { ChartAxis } from '../../Chart/models';
import useAxisY from '../Axes/useAxisY';
import { Line } from '../timeSeries/models';

interface UseComputeYAxisMaxCharactersProps {
  axis?: ChartAxis;
  base: number;
  displayedLines: Array<Line>;
  graphWidth: number;
  graphHeight: number;
  isHorizontal: boolean;
  leftScale: ScaleLinear<number, number, never>;
  rightScale: ScaleLinear<number, number, never>;
}

interface UseComputteYAxisMaxCharactersState {
  maxLeftAxisCharacters: number;
  maxRightAxisCharacters: number;
}

export const useComputeYAxisMaxCharacters = ({
  axis,
  base,
  displayedLines,
  graphWidth,
  graphHeight,
  isHorizontal,
  leftScale,
  rightScale
}: UseComputeYAxisMaxCharactersProps): UseComputteYAxisMaxCharactersState => {
  const { axisLeft, axisRight } = useAxisY({
    data: {
      baseAxis: base,
      lines: displayedLines,
      ...axis
    },
    graphHeight,
    graphWidth,
    isHorizontal
  });

  // Always add a space in case the algorithm does not compute the displayed value in axis.
  const maxLeftAxisCharacters = useMemo(() => {
    if (!leftScale) {
      return 0;
    }

    const ticks = getTicks(leftScale, axisLeft.numTicks);
    const formattedTicks = ticks.map(axisLeft.tickFormat);

    return isEmpty(formattedTicks)
      ? 2
      : Math.max(...formattedTicks.map((value) => value.length), 2) + 3;
  }, [leftScale, axisLeft]);

  const maxRightAxisCharacters = useMemo(() => {
    if (!rightScale) {
      return 0;
    }

    const ticks = getTicks(rightScale, axisRight.numTicks);
    const formattedTicks = ticks.map(axisRight.tickFormat);

    return isEmpty(formattedTicks)
      ? 2
      : Math.max(...formattedTicks.map((value) => value.length), 2) + 3;
  }, [rightScale, axisRight]);

  return {
    maxLeftAxisCharacters,
    maxRightAxisCharacters
  };
};
