import { ScaleLinear, ScaleTime } from 'd3-scale';
import { isNil, prop } from 'ramda';

import { bisectDate, getDates } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';

import useTickGraph from './useTickGraph';
import { GetYAnchorPoint } from './models';

import AnchorPoint from '.';

interface Props {
  areaColor: string;
  lineColor: string;
  metric: string;
  timeSeries: Array<TimeValue>;
  transparency: number;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const getYAnchorPoint = ({
  timeTick,
  timeSeries,
  yScale,
  metric
}: GetYAnchorPoint): number => {
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
  lineColor
}: Props): JSX.Element | null => {
  const { timeTick } = useTickGraph({
    timeSeries,
    xScale
  });

  if (isNil(timeTick)) {
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
    />
  );
};

export default RegularAnchorPoint;
