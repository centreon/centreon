import { useCallback, useMemo } from 'react';

import { BarStack, BarStackHorizontal } from '@visx/shape';
import { equals, keys, omit } from 'ramda';
import { scaleOrdinal } from '@visx/scale';
import { useSetAtom } from 'jotai';

import { useDeepMemo } from '../../utils';
import { Line, TimeValue } from '../common/timeSeries/models';

import { tooltipDataAtom } from './atoms';

interface HoverBarProps {
  barIndex: number;
  highlightedMetric: number;
}

export interface UseBarStackProps {
  isHorizontal: boolean;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  xScale;
  yScale;
}

interface UseBarStackState {
  BarStackComponent: typeof BarStack | typeof BarStackHorizontal;
  colorScale;
  commonBarStackProps;
  exitBar: () => void;
  hoverBar: (props: HoverBarProps) => () => void;
  lineKeys: Array<number>;
}

export const useBarStack = ({
  timeSeries,
  isHorizontal,
  lines,
  yScale,
  xScale
}: UseBarStackProps): UseBarStackState => {
  const setTooltipData = useSetAtom(tooltipDataAtom);

  const BarStackComponent = useMemo(
    () => (isHorizontal ? BarStack : BarStackHorizontal),
    [isHorizontal]
  );

  const lineKeys = useDeepMemo({
    deps: [timeSeries],
    variable: keys(omit(['timeTick'], timeSeries[0]))
  });
  const colors = useDeepMemo({
    deps: [lineKeys, lines],
    variable: lineKeys.map((key) => {
      const metric = lines.find(({ metric_id }) =>
        equals(metric_id, Number(key))
      );

      return metric?.lineColor || '';
    })
  });

  const colorScale = useMemo(
    () =>
      scaleOrdinal<number, string>({
        domain: lineKeys,
        range: colors
      }),
    [...lineKeys, ...colors]
  );

  const commonBarStackProps = isHorizontal
    ? {
        x: (d) => d.timeTick,
        xScale,
        yScale
      }
    : {
        xScale: yScale,
        y: (d) => d.timeTick,
        yScale: xScale
      };

  const hoverBar = useCallback(
    ({ highlightedMetric, barIndex }: HoverBarProps) =>
      (): void => {
        setTooltipData({
          data: lines.map((metric) => {
            return {
              metric,
              value: timeSeries[barIndex][metric.metric_id]
            };
          }),
          highlightedMetric,
          index: barIndex
        });
      },
    []
  );

  const exitBar = useCallback((): void => {
    setTooltipData(null);
  }, []);

  return {
    BarStackComponent,
    colorScale,
    commonBarStackProps,
    exitBar,
    hoverBar,
    lineKeys
  };
};
