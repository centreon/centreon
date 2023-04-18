import { memo } from 'react';

import { equals, isNil, map, pipe, not } from 'ramda';
import { ScaleLinear, ScaleTime } from 'd3-scale';

import { bisectDate } from '../../../timeSeries';
import { TimeValue } from '../../../timeSeries/models';

import AnchorPoint from '.';

interface StackData {
  data: TimeValue;
}

export type StackValue = [number, number, StackData];

interface Props {
  areaColor: string;
  displayTimeValues: boolean;
  graphHeight: number;
  graphWidth: number;
  lineColor: string;
  position: any;
  positionX: number;
  positionY: number;
  stackValues: Array<StackValue>;
  timeTick: Date | null;
  transparency: number;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

const test = 'data';

const getStackedDates = (stackValues: Array<StackValue>): Array<Date> => {
  const toTimeTick = (stackValue: StackValue): string =>
    stackValue[test].timeTick;
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
  displayTimeValues,
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

export default memo(
  StackedAnchorPoint,
  (prevProps, nextProps) =>
    equals(prevProps.timeTick, nextProps.timeTick) &&
    equals(prevProps.stackValues, nextProps.stackValues) &&
    equals(prevProps.position, nextProps.position)
);
