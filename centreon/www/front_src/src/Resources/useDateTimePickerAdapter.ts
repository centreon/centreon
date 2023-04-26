/* eslint-disable class-methods-use-this */
import { useCallback } from 'react';

import DayjsAdapter from '@date-io/dayjs';
import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals, isNil, not, pipe } from 'ramda';

import { useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

interface UseDateTimePickerAdapterProps {
  Adapter;
  formatKeyboardValue: (value?: string) => string | undefined;
  getDestinationAndConfiguredTimezoneOffset: (
    destinationTimezone?: string
  ) => number;
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

const isSummerDate = equals(DSTState.SUMMER);

const useDateTimePickerAdapter = (): UseDateTimePickerAdapterProps => {
  const { timezone, locale } = useAtomValue(userAtom);
  const { format } = useLocaleDateTimeFormat();

  const normalizedLocale = locale.substring(0, 2);

  const toTimezonedDate = ({
    date,
    timeZone = undefined
  }: ToTimezonedDateProps): Date => {
    if (isNil(timeZone)) {
      return new Date(date.toLocaleString('en-US'));
    }

    return new Date(date.toLocaleString('en-US', { timeZone }));
  };

  const getDestinationAndConfiguredTimezoneOffset = (
    destinationTimezone?: string
  ): number => {
    const now = new Date();
    const currentTimezoneDate = toTimezonedDate({
      date: now,
      timeZone: destinationTimezone
    });
    const destinationTimezoneDate = toTimezonedDate({
      date: now,
      timeZone: timezone
    });

    return Math.floor(
      (currentTimezoneDate.getTime() - destinationTimezoneDate.getTime()) /
        60 /
        60 /
        1000
    );
  };

  const getDSTState = useCallback(
    (date: dayjs.Dayjs): DSTState => {
      const currentYear = toTimezonedDate({
        date: new Date(),
        timeZone: timezone
      }).getFullYear();

      const january = dayjs(new Date(currentYear, 0, 1))
        .tz(timezone)
        .utcOffset();
      const july = dayjs(new Date(currentYear, 6, 1)).tz(timezone).utcOffset();

      if (equals(january, july)) {
        return DSTState.NODST;
      }

      return july === date.utcOffset() ? DSTState.SUMMER : DSTState.WINTER;
    },
    [timezone]
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
  class Adapter extends DayjsAdapter {
    public formatByString = (value, formatKey: string): string => {
      return format({
        date: value.tz(timezone),
        formatString: formatKey
      });
    };

    public isEqual = (value, comparing): boolean => {
      if (value === null && comparing === null) {
        return true;
      }

      return equals(
        format({ date: value, formatString: 'LT' }),
        format({ date: comparing, formatString: 'LT' })
      );
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

    public getHours = (date): number => {
      return date.tz(timezone).get('hour');
    };

    public setHours = (date: dayjs.Dayjs, count: number): dayjs.Dayjs => {
      const dateDSTState = getDSTState(date.tz(timezone));

      const isNotASummerDate = pipe(isSummerDate, not)(dateDSTState);
      const isInUTC = equals(
        getDestinationAndConfiguredTimezoneOffset('UTC'),
        0
      );

      if ((isInUTC && isNotASummerDate) || equals('UTC', timezone)) {
        return date
          .tz(timezone)
          .set('hour', count - getDestinationAndConfiguredTimezoneOffset());
      }

      return date.tz(timezone).set('hour', count);
    };

    public isSameHour = (
      date: dayjs.Dayjs,
      comparing: dayjs.Dayjs
    ): boolean => {
      return date.tz(timezone).isSame(comparing.tz(timezone), 'hour');
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
      return date.endOf('month') as dayjs.Dayjs;
    };

    public isSameMonth = (
      date: dayjs.Dayjs,
      comparing: dayjs.Dayjs
    ): boolean => {
      return date.tz(timezone).isSame(comparing.tz(timezone), 'month');
    };

    public getMonth = (date: dayjs.Dayjs): number => {
      return date.month();
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

      const startOfWeek = date.startOf('month').startOf('week');
      const endOfWeek = date.endOf('month').endOf('week');

      const start = startOfWeek.endOf('day');

      const end = endOfWeek.endOf('day');
      const customStart = isMorning
        ? startOfWeek.startOf('day')
        : startOfWeek.endOf('day');

      const customEnd = isMorning
        ? endOfWeek.startOf('day')
        : endOfWeek.endOf('day');

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

      const dateDSTState = getDSTState(dateWithTimezone);

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
    formatKeyboardValue,
    getDestinationAndConfiguredTimezoneOffset
  };
};

export default useDateTimePickerAdapter;
