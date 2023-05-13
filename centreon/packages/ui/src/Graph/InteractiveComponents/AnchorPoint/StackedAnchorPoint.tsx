import { ScaleLinear, ScaleTime } from 'd3-scale';
import { isNil, map, not, pipe } from 'ramda';

import { bisectDate } from '../../timeSeries';

import { StackValue } from './models';

import AnchorPoint from '.';

interface Props {
  areaColor: string;
  displayTimeValues?: boolean;
  graphHeight: number;
  graphWidth: number;
  lineColor: string;
  positionX?: number;
  positionY?: number;
  stackValues: Array<StackValue>;
  timeTick?: Date;
  transparency: number;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const getStackedDates = (stackValues: Array<StackValue>): Array<Date> => {
  const toTimeTick = (stackValue: StackValue): string =>
    stackValue[2]?.data?.timeTick;

  const toDate = (tick: string): Date => new Date(tick);

  return pipe(map(toTimeTick), map(toDate))(stackValues);
};

const getYAnchorPoint = ({
  timeTick,
  stackValues,
  yScale
}: Pick<Props, 'timeTick' | 'stackValues' | 'yScale'>): number => {
  const index = bisectDate(getStackedDates(stackValues), timeTick);
  const timeValue = stackValues[index];

  return yScale(timeValue[1] as number);
};

const StackedAnchorPoint = ({
  xScale,
  yScale,
  stackValues,
  timeTick,
  areaColor,
  transparency,
  lineColor,
  displayTimeValues = true,
  ...rest
}: Props): JSX.Element | null => {
  if (isNil(timeTick) || not(displayTimeValues)) {
    return null;
  }
  if (isNil(timeTick)) {
    return null;
  }
  const xAnchorPoint = xScale(timeTick);

  const yAnchorPoint = getYAnchorPoint({
    stackValues,
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

export default StackedAnchorPoint;
