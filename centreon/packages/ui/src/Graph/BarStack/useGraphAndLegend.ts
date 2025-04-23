import { scaleBand, scaleLinear } from '@visx/scale';
import { pluck } from 'ramda';
import { useMemo } from 'react';
import { BarType } from './models';

interface UseGraphandLegendProps {
  data: Array<BarType>;
  isVerticalBar: boolean;
  total: number;
  width: number;
  height: number;
}

interface UseGraphAndLegendState {
  barStackData: Record<string, number>;
  xScale;
  yScale;
  keys: Array<string>;
}

export const useGraphAndLegend = ({
  data,
  isVerticalBar,
  total,
  width,
  height
}: UseGraphandLegendProps): UseGraphAndLegendState => {
  const dataWithNonEmptyValue = useMemo(
    () => data.filter(({ value }) => value),
    [data]
  );

  const keys = useMemo(
    () => pluck('label', dataWithNonEmptyValue),
    [dataWithNonEmptyValue]
  );

  const barStackData = useMemo(
    () =>
      dataWithNonEmptyValue.reduce((acc, { label, value }) => {
        acc[label] = value;

        return acc;
      }, {}),
    [dataWithNonEmptyValue]
  );

  const yScale = useMemo(
    () =>
      isVerticalBar
        ? scaleLinear({
            domain: [0, total],
            range: [height, 0]
          })
        : scaleBand({
            domain: [0, 0],
            padding: 0,
            range: [height, 0]
          }),
    [isVerticalBar, total, height]
  );

  const xScale = useMemo(
    () =>
      isVerticalBar
        ? scaleBand({
            domain: [0, 0],
            padding: 0,
            range: [0, width]
          })
        : scaleLinear({
            domain: [0, total],
            range: [0, width]
          }),
    [total, width, isVerticalBar]
  );

  return {
    barStackData,
    xScale,
    yScale,
    keys
  };
};
