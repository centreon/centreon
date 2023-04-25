import { memo } from 'react';

import { equals, isNil, not, prop } from 'ramda';
import { ScaleLinear, ScaleTime } from 'd3-scale';

import { getDates, bisectDate } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';

import AnchorPoint from '.';

interface Props {
  areaColor: string;
  displayTimeValues: boolean;
  graphHeight: number;
  graphWidth: number;
  lineColor: string;
  metric: string;
  position: any;
  positionX: number;
  positionY: number;
  timeSeries: Array<TimeValue>;
  timeTick: Date | null;
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

const RegularAnchorPoint = ({
  xScale,
  yScale,
  metric,
  timeSeries,
  timeTick,
  areaColor,
  transparency,
  lineColor,
  displayTimeValues,
  ...rest
}: Props): JSX.Element | null => {
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
      transparency={transparency}
      x={xAnchorPoint}
      y={yAnchorPoint}
      {...rest}
    />
  );
};

export default memo(
  RegularAnchorPoint,
  (prevProps, nextProps) =>
    equals(prevProps.timeTick, nextProps.timeTick) &&
    equals(prevProps.timeSeries, nextProps.timeSeries) &&
    equals(prevProps.position, nextProps.position)
);
