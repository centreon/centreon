import dayjs, { Dayjs } from 'dayjs';

import { usePluralizedTranslation } from '@centreon/ui';
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
          value: diffDuration.years(),
          unit: pluralizedT({ label: labelYear, count: diffDuration.years() })
        },
        {
          value: diffDuration.months(),
          unit: pluralizedT({ label: labelMonth, count: diffDuration.hours() })
        },
        {
          value: diffDuration.days(),
          unit: pluralizedT({ label: labelDay, count: diffDuration.days() })
        },
        {
          value: diffDuration.hours(),
          unit: pluralizedT({ label: labelHour, count: diffDuration.hours() })
        },
        {
          value: diffDuration.minutes(),
          unit: pluralizedT({
            label: labelMinute,
            count: diffDuration.minutes()
          })
        }
      ];

      const readableUnits = timeUnits
        .filter((unit) => unit.value > 0)
        .map((unit) => `${unit.value} ${unit.unit}`);

      return readableUnits.slice(0, 2).join(', ');
    },
    []
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
