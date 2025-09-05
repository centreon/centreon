import { isEmpty } from 'ramda';
import { useMemo } from 'react';
import { ChartAxis } from '../../Chart/models';
import { Data } from '../Axes/models';
import { Thresholds } from '../models';
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
        threshold: thresholds?.critical ?? [],
        axisUnit: axis?.axisYLeft?.unit ?? firstUnit,
        timeSeries: graphData?.timeSeries ?? [],
        thresholdUnit,
        lines: graphData?.lines ?? [],
        base: graphData?.baseAxis
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
        threshold: thresholds?.critical ?? [],
        axisUnit: axis?.axisYRight?.unit ?? secondUnit,
        timeSeries: graphData.timeSeries ?? [],
        thresholdUnit,
        lines: graphData.lines ?? [],
        base: graphData.baseAxis
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

  const maxLeftAxisCharacters = useMemo(
    () =>
      isEmpty(maxLeftValue)
        ? 2
        : Math.max(...maxLeftValue.map((value) => value.length), 2),
    [maxLeftValue]
  );

  const maxRightAxisCharacters = useMemo(
    () =>
      isEmpty(maxRightValue)
        ? 5
        : Math.max(...maxRightValue.map((value) => value.length), 5),
    [maxRightValue]
  );

  return {
    maxLeftAxisCharacters,
    maxRightAxisCharacters
  };
};
