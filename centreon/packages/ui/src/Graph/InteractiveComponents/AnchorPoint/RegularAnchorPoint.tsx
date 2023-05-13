import { ScaleLinear, ScaleTime } from 'd3-scale';
import { isNil, not, prop } from 'ramda';

import { bisectDate, getDates } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';

import useTickGraph from './useTickGraph';

import AnchorPoint from '.';

interface Props {
  areaColor: string;
  displayTimeValues?: boolean;
  graphHeight: number;
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

const RegularAnchorPoint = ({
  xScale,
  yScale,
  metric,
  timeSeries,
  areaColor,
  transparency,
  lineColor,
  displayTimeValues = true,
  ...rest
}: Props): JSX.Element | null => {
  const { timeTick, positionX, positionY } = useTickGraph({
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

export default RegularAnchorPoint;
