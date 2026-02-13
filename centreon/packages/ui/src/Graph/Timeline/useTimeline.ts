import { usePluralizedTranslation } from '@centreon/ui';

import dayjs, { type Dayjs } from 'dayjs';
import { lt } from 'ramda';
import { useCallback } from 'react';

import {
  labelDay,
  labelHour,
  labelMinute,
  labelMonth,
  labelYear
} from './translatedLabel';

interface StartEndProps {
  start: Dayjs;
  end: Dayjs;
}

interface GetWidthProps extends StartEndProps {
  timezone: string;
  xScale;
}

interface UseTimelineState {
  getTimeDifference: (props: StartEndProps) => string;
  getWidth: (props: GetWidthProps) => number;
}

export const useTimeline = (): UseTimelineState => {
  const { pluralizedT } = usePluralizedTranslation();

  const getTimeDifference = useCallback(
    ({ start, end }: StartEndProps): string => {
      const diffInMilliseconds = end.diff(start);
      const diffDuration = dayjs.duration(diffInMilliseconds);

      const timeUnits = [
        {
          unit: pluralizedT({ count: diffDuration.years(), label: labelYear }),
          value: diffDuration.years()
        },
        {
          unit: pluralizedT({
            count: diffDuration.months(),
            label: labelMonth
          }),
          value: diffDuration.months()
        },
        {
          unit: pluralizedT({ count: diffDuration.days(), label: labelDay }),
          value: diffDuration.days()
        },
        {
          unit: pluralizedT({ count: diffDuration.hours(), label: labelHour }),
          value: diffDuration.hours()
        },
        {
          unit: pluralizedT({
            count: diffDuration.minutes(),
            label: labelMinute
          }),
          value: diffDuration.minutes()
        }
      ];

      const readableUnits = timeUnits
        .filter((unit) => unit.value > 0)
        .map((unit) => `${unit.value} ${unit.unit}`);

      return readableUnits.slice(0, 2).join(', ');
    },
    [pluralizedT]
  );

  const getWidth = useCallback(
    ({ start, end, timezone, xScale }: GetWidthProps): number => {
      const baseWidth =
        xScale(dayjs(end).tz(timezone)) - xScale(dayjs(start).tz(timezone));

      if (Number.isNaN(baseWidth) || lt(baseWidth, 0)) {
        return 0;
      }

      return baseWidth;
    },
    []
  );

  return {
    getTimeDifference,
    getWidth
  };
};
