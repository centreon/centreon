import type { ScaleLinear, ScaleTime } from 'd3-scale';
import { isNil, map, pipe } from 'ramda';

import { bisectDate } from '../../../common/timeSeries';
import type { TimeValue } from '../../../common/timeSeries/models';
import AnchorPoint from '.';
import type { StackValue } from './models';
import useTickGraph from './useTickGraph';

interface Props {
  lineColor: string;
  stackValues: Array<StackValue>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
  hasSecondUnit?: boolean;
  maxLeftAxisCharacters: number;
}

interface GetYAnchorPoint {
  stackValues: Array<StackValue>;
  timeTick: Date | null;
  yScale: ScaleTime<number, number>;
}

const getStackedDates = (stackValues: Array<StackValue>): Array<Date> => {
  const toTimeTick = (stackValue): string => stackValue?.data?.timeTick;

  const toDate = (tick: string): Date => new Date(tick);

  return pipe(map(toTimeTick), map(toDate))(stackValues);
};

export const getYAnchorPoint = ({
  timeTick,
  stackValues,
  yScale
}: GetYAnchorPoint): number | null => {
  const index = bisectDate(getStackedDates(stackValues), timeTick);
  const timeValue = stackValues[index];
  const { key } = stackValues;

  if (isNil(timeValue.data[key])) {
    return null;
  }

  return yScale(timeValue[0] as number);
};

const StackedAnchorPoint = ({
  xScale,
  yScale,
  stackValues,
  timeSeries,
  lineColor,
  hasSecondUnit,
  maxLeftAxisCharacters
}: Props): JSX.Element | null => {
  const { tickAxisBottom: timeTick } = useTickGraph({
    hasSecondUnit,
    maxLeftAxisCharacters,
    timeSeries,
    xScale
  });

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
    <AnchorPoint lineColor={lineColor} x={xAnchorPoint} y={yAnchorPoint} />
  );
};

export default StackedAnchorPoint;
