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
  maxLeftAxisCharacters: number;
}

interface GetYAnchorPoint {
  stackValues: Array<StackValue>;
  timeTick: Date | null;
  yScale: ScaleLinear<number, number> | ScaleTime<number, number>;
}

const getStackedDates = (stackValues: Array<StackValue>): Array<Date> => {
  const toTimeTick = (stackValue: { data?: { timeTick: string } }): string =>
    stackValue?.data?.timeTick as string;

  const toDate = (tick: string): Date => new Date(tick);

  return pipe(
    map(toTimeTick),
    map(toDate)
  )(stackValues as unknown as Array<{ data?: { timeTick: string } }>);
};

export const getYAnchorPoint = ({
  timeTick,
  stackValues,
  yScale
}: GetYAnchorPoint): number | null => {
  const index = bisectDate(getStackedDates(stackValues), timeTick);
  const timeValue = stackValues[index];
  // @ts-expect-error - suppressing pre-existing type mismatch
  const { key } = stackValues;

  // @ts-expect-error - suppressing pre-existing type mismatch
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
  maxLeftAxisCharacters
}: Props): JSX.Element | null => {
  const { tickAxisBottom: timeTick } = useTickGraph({
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
