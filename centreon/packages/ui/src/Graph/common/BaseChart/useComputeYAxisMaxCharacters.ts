import { isEmpty } from 'ramda';
import { useMemo } from 'react';

import type { ChartAxis } from '../../Chart/models';
import type { Data } from '../Axes/models';
import type { Thresholds } from '../models';
import { getFormattedAxisValues } from '../utils';

interface UseComputeYAxisMaxCharactersProps {
  firstUnit: string;
  secondUnit: string;
  thresholdUnit?: string;
  thresholds?: Thresholds;
  graphData: Data;
  axis?: ChartAxis;
}

interface UseComputteYAxisMaxCharactersState {
  maxLeftAxisCharacters: number;
  maxRightAxisCharacters: number;
}

export const useComputeYAxisMaxCharacters = ({
  thresholds,
  firstUnit,
  secondUnit,
  graphData,
  axis,
  thresholdUnit
}: UseComputeYAxisMaxCharactersProps): UseComputteYAxisMaxCharactersState => {
  const maxLeftValue = useMemo(
    () =>
      getFormattedAxisValues({
        axisUnit: axis?.axisYLeft?.unit ?? firstUnit,
        base: graphData?.baseAxis,
        lines: graphData?.lines ?? [],
        threshold: thresholds?.critical ?? [],
        thresholdUnit,
        timeSeries: graphData?.timeSeries ?? []
      }),
    [
      thresholds?.critical,
      axis?.axisYLeft?.unit,
      firstUnit,
      graphData?.timeSeries,
      thresholdUnit,
      graphData?.lines,
      graphData?.baseAxis
    ]
  );

  const maxRightValue = useMemo(
    () =>
      getFormattedAxisValues({
        axisUnit: axis?.axisYRight?.unit ?? secondUnit,
        base: graphData.baseAxis,
        lines: graphData.lines ?? [],
        threshold: thresholds?.critical ?? [],
        thresholdUnit,
        timeSeries: graphData.timeSeries ?? []
      }),
    [
      thresholds?.critical,
      axis?.axisYRight?.unit,
      secondUnit,
      graphData.timeSeries,
      thresholdUnit,
      graphData.lines,
      graphData.baseAxis
    ]
  );

  // Always add a character space in case the algorithm does not compute the displayed value in axis.
  const maxLeftAxisCharacters = useMemo(
    () =>
      isEmpty(maxLeftValue)
        ? 2
        : Math.max(...maxLeftValue.map((value) => value.length), 2) + 1,
    [maxLeftValue]
  );

  const maxRightAxisCharacters = useMemo(
    () =>
      isEmpty(maxRightValue)
        ? 5
        : Math.max(...maxRightValue.map((value) => value.length), 5) + 1,
    [maxRightValue]
  );

  return {
    maxLeftAxisCharacters,
    maxRightAxisCharacters
  };
};
