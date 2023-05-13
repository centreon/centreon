import { MutableRefObject } from 'react';

import { ScaleLinear, ScaleTime } from 'd3-scale';
import { isNil, not, prop } from 'ramda';

import { useMemoComponent } from '@centreon/ui';

import { bisectDate, getDates } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';

import useAnchorPoint from './useAnchorPoint';

import AnchorPoint from '.';

interface Props {
  areaColor: string;
  displayTimeValues?: boolean;
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  lineColor: string;
  metric: string;
  positionX?: number;
  positionY?: number;
  timeSeries: Array<TimeValue>;
  timeTick?: Date;
  transparency: number;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const getYAnchorPoint = ({
  timeTick,
  timeSeries,
  yScale,
  metric
}: Pick<Props, 'timeTick' | 'timeSeries' | 'yScale' | 'metric'>): number => {
  const index = bisectDate(getDates(timeSeries), timeTick);
  const timeValue = timeSeries[index];

  return yScale(prop(metric, timeValue) as number);
};

const Test = ({
  xScale,
  yScale,
  metric,
  timeSeries,
  areaColor,
  transparency,
  lineColor,
  graphSvgRef,
  displayTimeValues = true,
  ...rest
}: Props): JSX.Element | null => {
  const { timeTick, positionX, positionY } = useAnchorPoint({
    graphSvgRef,
    timeSeries,
    xScale
  });

  if (isNil(timeTick) || not(displayTimeValues)) {
    return null;
  }

  if (isNil(timeTick) || not(displayTimeValues)) {
    return null;
  }
  const xAnchorPoint = xScale(timeTick);

  const yAnchorPoint = getYAnchorPoint({
    metric,
    timeSeries,
    timeTick,
    yScale
  });

  if (isNil(yAnchorPoint)) {
    return null;
  }

  return (
    <AnchorPoint
      areaColor={areaColor}
      lineColor={lineColor}
      positionX={positionX}
      positionY={positionY}
      transparency={transparency}
      x={xAnchorPoint}
      y={yAnchorPoint}
      {...rest}
    />
  );
};

const RegularAnchorPoint = ({
  xScale,
  yScale,
  metric,
  timeSeries,
  areaColor,
  transparency,
  lineColor,
  graphSvgRef,
  displayTimeValues = true,
  ...rest
}: Props): JSX.Element => {
  const args = {
    areaColor,
    displayTimeValues,
    graphSvgRef,
    lineColor,
    metric,
    timeSeries,
    transparency,
    xScale,
    yScale,
    ...rest
  };

  return useMemoComponent({
    Component: <Test {...args} />,
    memoProps: [timeSeries, xScale]
  });
};

export default RegularAnchorPoint;
