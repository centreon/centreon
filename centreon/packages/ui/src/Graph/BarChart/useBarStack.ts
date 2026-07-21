import { scaleOrdinal } from '@visx/scale';
import { BarStack, BarStackHorizontal } from '@visx/shape';
import type { ScaleBand, ScaleLinear, ScaleOrdinal } from 'd3-scale';
import { useSetAtom } from 'jotai';
import { equals, keys, omit } from 'ramda';
import type { ComponentType } from 'react';
import { useCallback, useMemo } from 'react';

import { useDeepMemo } from '../../utils';
import type { Line, TimeValue } from '../common/timeSeries/models';
import { tooltipDataAtom } from './atoms';

interface HoverBarProps {
  barIndex: number;
  highlightedMetric: number;
}

export interface UseBarStackProps {
  isHorizontal: boolean;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleBand<number> | ScaleLinear<number, number>;
  yScale: ScaleBand<number> | ScaleLinear<number, number>;
}

// biome-ignore lint/suspicious/noExplicitAny: scale union with visx
type CommonBarStackProps = Record<string, any>;

interface UseBarStackState {
  // biome-ignore lint/suspicious/noExplicitAny: visx BarStack/BarStackHorizontal union
  BarStackComponent: ComponentType<any>;
  colorScale: ScaleOrdinal<number, string>;
  commonBarStackProps: CommonBarStackProps;
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
    () =>
      // biome-ignore lint/suspicious/noExplicitAny: visx BarStack/BarStackHorizontal union
      (isHorizontal ? BarStack : BarStackHorizontal) as ComponentType<any>,
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
    [...lineKeys, ...colors, colors, lineKeys]
  );

  const commonBarStackProps = isHorizontal
    ? {
        x: (d: TimeValue) => d.timeTick,
        xScale,
        yScale
      }
    : {
        xScale: yScale,
        y: (d: TimeValue) => d.timeTick,
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
    [lines, timeSeries, setTooltipData]
  );

  const exitBar = useCallback((): void => {
    setTooltipData(null);
  }, [setTooltipData]);

  return {
    BarStackComponent,
    colorScale,
    commonBarStackProps,
    exitBar,
    hoverBar,
    lineKeys
  };
};
