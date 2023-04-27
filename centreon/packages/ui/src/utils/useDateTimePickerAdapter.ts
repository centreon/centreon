/* eslint-disable class-methods-use-this */
import { useCallback } from 'react';

import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals, isNil, not, pipe } from 'ramda';

import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';

import { useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

interface GetDestinationAndConfiguredTimezoneOffsetProps {
  endTimezone?: string;
  startTimezone?: string;
}

interface UseDateTimePickerAdapterProps {
  Adapter;
  desktopPickerMediaQuery: string;
}

enum DSTState {
  SUMMER,
  WINTER,
  NODST
}

interface ToTimezonedDateProps {
  date: Date;
  timeZone?: string;
}

interface GetDSTStateProps {
  date: dayjs.Dayjs;
  timezoneToUse?: string;
}

export const useDateTimePickerAdapter = (): UseDateTimePickerAdapterProps => {
  const { timezone, locale } = useAtomValue(userAtom);
  const { format } = useLocaleDateTimeFormat();

  const normalizedLocale = locale.substring(0, 2);

  const desktopPickerMediaQuery =
    '@media (pointer: fine) or (min-width: 1024px)';

  const toTimezonedDate = ({
    date,
    timeZone = undefined
  }: ToTimezonedDateProps): Date => {
    if (isNil(timeZone)) {
      return new Date(date.toLocaleString('en-US'));
    }

    return new Date(date.toLocaleString('en-US', { timeZone }));
  };

  const getDestinationAndConfiguredTimezoneOffset = ({
    startTimezone = undefined,
    endTimezone = timezone
  }: GetDestinationAndConfiguredTimezoneOffsetProps): number => {
    const now = new Date();
    const currentTimezoneDate = toTimezonedDate({
      date: now,
      timeZone: startTimezone
    });
    const destinationTimezoneDate = toTimezonedDate({
      date: now,
      timeZone: endTimezone
    });

    return Math.floor(
      (currentTimezoneDate.getTime() - destinationTimezoneDate.getTime()) /
        60 /
        60 /
        1000
    );
  };

  const getDSTState = useCallback(
    ({ date, timezoneToUse }: GetDSTStateProps): DSTState => {
      const hasNoTimezone = isNil(timezoneToUse);
      const currentYear = toTimezonedDate({
        date: new Date(),
        timeZone: timezoneToUse
      }).getFullYear();

      const january = hasNoTimezone
        ? dayjs(new Date(currentYear, 0, 1)).utcOffset()
        : dayjs(new Date(currentYear, 0, 1)).tz(timezoneToUse).utcOffset();
      const july = hasNoTimezone
        ? dayjs(new Date(currentYear, 6, 1)).utcOffset()
        : dayjs(new Date(currentYear, 6, 1)).tz(timezoneToUse).utcOffset();

      if (equals(january, july)) {
        return DSTState.NODST;
      }

      return july === date.tz().utcOffset() ? DSTState.SUMMER : DSTState.WINTER;
    },
    [timezone]
  );

  const getDSTStateForCurrentTimezone = useCallback(
    (date: dayjs.Dayjs): DSTState => {
      return getDSTState({ date });
    },
    []
  );

  const formatKeyboardValue = (value?: string): string | undefined => {
    if (equals(normalizedLocale, 'en') || isNil(value)) {
      return value;
    }
    const month = value.substring(0, 2);
    const day = value.substring(3, 5);

    const newValue = `${day}/${month}/${value.substring(6, 16)}`;

    return newValue;
  };

  interface Chunk {
    array: Array<unknown>;
    size: number;
  }
  class Adapter extends AdapterDayjs {
    public formatByString = (value, formatKey: string): string => {
      return format({
        date: value.tz(timezone).toDate(),
        formatString: formatKey
      });
    };

    public format = (date: dayjs.Dayjs, formatKey: string): string => {
      return this.formatByString(
        date.tz(timezone, true),
        this.formats[formatKey]
      );
    };

    public startOfWeek = (date: dayjs.Dayjs): dayjs.Dayjs => {
      if (date.tz(timezone).isUTC()) {
        return date.tz(timezone).startOf('week').utc();
      }

      return date.tz(timezone).startOf('week');
    };

    public setMinutes = (date: dayjs.Dayjs, count: number): dayjs.Dayjs => {
      return date.minute(count);
    };

    public setHours = (date: dayjs.Dayjs, count: number): dayjs.Dayjs => {
      return date.hour(count);
    };

    public isSameDay = (date: dayjs.Dayjs, comparing: dayjs.Dayjs): boolean => {
      const isSameYearAndMonth = this.isSameYear(date, comparing)
        ? this.isSameMonth(date, comparing)
        : false;

      return (
        isSameYearAndMonth &&
        date.tz(timezone).isSame(comparing.tz(timezone), 'day')
      );
    };

    public startOfDay = (date: dayjs.Dayjs): dayjs.Dayjs => {
      return date.tz(timezone).startOf('day') as dayjs.Dayjs;
    };

    public endOfDay = (date: dayjs.Dayjs): dayjs.Dayjs => {
      return date.tz(timezone).endOf('day') as dayjs.Dayjs;
    };

    public startOfMonth = (date: dayjs.Dayjs): dayjs.Dayjs => {
      if (date.tz(timezone).isUTC()) {
        return date.tz(timezone).utc();
      }

      return date.tz(timezone);
    };

    public endOfMonth = (date: dayjs.Dayjs): dayjs.Dayjs => {
      return date.tz(timezone).endOf('month') as dayjs.Dayjs;
    };

    public isSameMonth = (
      date: dayjs.Dayjs,
      comparing: dayjs.Dayjs
    ): boolean => {
      return date.tz(timezone).isSame(comparing.tz(timezone), 'month');
    };

    public getMonth = (date: dayjs.Dayjs): number => {
      return date.tz(timezone).month();
    };

    public isAfter = (date: dayjs.Dayjs, value: dayjs.Dayjs): boolean => {
      return date.isAfter(value.endOf('month'));
    };

    public isBefore = (date: dayjs.Dayjs, value: dayjs.Dayjs): boolean => {
      return date.isBefore(value.startOf('month'));
    };

    public getDaysInMonth = (date: dayjs.Dayjs): number => {
      return date.tz(timezone).daysInMonth();
    };

    public getWeekdays = (): Array<string> => {
      const start = dayjs().locale(locale).tz(timezone).startOf('week');

      return [0, 1, 2, 3, 4, 5, 6].map((diff) =>
        this.formatByString(start.add(diff, 'day'), 'dd')
      );
    };

    public getChunkFromArray = ({ array, size }: Chunk): Array<unknown> => {
      if (!array.length) {
        return [];
      }
      const head = array.slice(0, size);
      const tail = array.slice(size);

      return [head, ...this.getChunkFromArray({ array: tail, size })];
    };

    public getWeekArray = (date: dayjs.Dayjs): Array<Array<dayjs.Dayjs>> => {
      const isMorning = equals(dayjs().tz(timezone).format('a'), 'am');
      const startOfWeek = date.tz(timezone).startOf('month').startOf('week');
      const endOfWeek = date.tz(timezone).endOf('month').endOf('week');
      const start = startOfWeek.startOf('day');
      const end = endOfWeek.endOf('day');
      const customStart = isMorning
        ? startOfWeek.startOf('day')
        : startOfWeek.startOf('day');
      const customEnd = isMorning
        ? endOfWeek.startOf('day')
        : endOfWeek.startOf('day');
      const currentStart = start.isUTC()
        ? start.tz(timezone, true)
        : customStart;
      const currentEnd = end.isUTC() ? end.tz(timezone, true) : customEnd;
      const numberOfDaysInCurrentMonth = currentEnd.diff(
        currentStart,
        'd',
        true
      );
      const daysOfMonthWithTimezone = [
        ...Array(Math.round(numberOfDaysInCurrentMonth)).keys()
      ].reduce(
        (acc, _, currentIndex) => {
          if (acc[currentIndex].isUTC()) {
            const newCurrent = acc[currentIndex]
              .utc()
              .add(1, 'day')
              .tz(timezone, true);

            return [...acc, newCurrent];
          }
          const newCurrent = acc[currentIndex].add(1, 'day');

          return [...acc, newCurrent];
        },
        [currentStart]
      );
      const weeksArray = this.getChunkFromArray({
        array: daysOfMonthWithTimezone,
        size: 7
      });

      return weeksArray as Array<Array<dayjs.Dayjs>>;
    };

    public mergeDateAndTime = (
      date: dayjs.Dayjs,
      time: dayjs.Dayjs
    ): dayjs.Dayjs => {
      const dateWithTimezone = date.tz(timezone).startOf('day');
      const timeWithTimezone = time.tz(timezone);
      const dateDSTState = getDSTState({
        date: dateWithTimezone,
        timezoneToUse: timezone
      });
      const dateDSTStateWithCurrentTimezone =
        getDSTStateForCurrentTimezone(date);

      if (equals(dateDSTStateWithCurrentTimezone, DSTState.WINTER)) {
        return dateWithTimezone
          .add(
            timeWithTimezone.hour() -
              getDestinationAndConfiguredTimezoneOffset({
                endTimezone: 'UTC',
                startTimezone: dayjs.tz.guess()
              }),
            'hour'
          )
          .add(timeWithTimezone.minute(), 'minute')
          .add(timeWithTimezone.second(), 'second');
      }

      if (not(equals(dateDSTState, DSTState.SUMMER))) {
        return dateWithTimezone
          .add(timeWithTimezone.hour(), 'hour')
          .add(timeWithTimezone.minute(), 'minute')
          .add(timeWithTimezone.second(), 'second');
      }

      return dateWithTimezone
        .hour(timeWithTimezone.hour())
        .minute(timeWithTimezone.minute())
        .second(timeWithTimezone.second());
    };
  }

  return {
    Adapter,
    desktopPickerMediaQuery
  };
};
