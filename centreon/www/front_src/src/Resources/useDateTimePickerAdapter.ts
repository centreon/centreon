/* eslint-disable class-methods-use-this */
import { useCallback } from 'react';

import DayjsAdapter from '@date-io/dayjs';
import dayjs from 'dayjs';
import { equals, isNil, not, pipe } from 'ramda';
import { useAtomValue } from 'jotai/utils';

import { useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

interface UseDateTimePickerAdapterProps {
  Adapter;
  formatKeyboardValue: (value?: string) => string | undefined;
  getDestinationAndConfiguredTimezoneOffset: (
    destinationTimezone?: string,
  ) => number;
}

enum DSTState {
  SUMMER,
  WINTER,
  NODST,
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
    timeZone = undefined,
  }: ToTimezonedDateProps): Date => {
    if (isNil(timeZone)) {
      return new Date(date.toLocaleString('en-US'));
    }

    return new Date(date.toLocaleString('en-US', { timeZone }));
  };

  const getDestinationAndConfiguredTimezoneOffset = (
    destinationTimezone?: string,
  ): number => {
    const now = new Date();
    const currentTimezoneDate = toTimezonedDate({
      date: now,
      timeZone: destinationTimezone,
    });
    const destinationTimezoneDate = toTimezonedDate({
      date: now,
      timeZone: timezone,
    });

    return Math.floor(
      (currentTimezoneDate.getTime() - destinationTimezoneDate.getTime()) /
        60 /
        60 /
        1000,
    );
  };

  const getDSTState = useCallback(
    (date: dayjs.Dayjs): DSTState => {
      const currentYear = toTimezonedDate({
        date: new Date(),
        timeZone: timezone,
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
    [timezone],
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

  const withTimeZone = (dayjs: any, currentTimezone?: string) =>
    !currentTimezone
      ? dayjs
      : (...args): dayjs.Dayjs => {
          return dayjs(...args)
            .tz(currentTimezone)
            .set('h', 0)
            .set('m', 0)
            .set('s', 0)
            .set('ms', 0);
        };

  class Adapter extends DayjsAdapter {
    public prevMonth;

    public nextMonth;

    public constructor() {
      super();
      this.dayjs = withTimeZone(this.rawDayJsInstance, timezone);
    }

    public formatByString = (value, formatKey: string): string => {
      return format({
        date: value.tz(timezone, true),
        formatString: formatKey,
      });
    };

    public isEqual = (value, comparing): boolean => {
      if (value === null && comparing === null) {
        return true;
      }

      return equals(
        format({ date: value, formatString: 'LT' }),
        format({ date: comparing, formatString: 'LT' }),
      );
    };

    public getHours = (date): number => {
      return date.tz(timezone).get('hour');
    };

    public setHours = (date: dayjs.Dayjs, count: number): dayjs.Dayjs => {
      const dateDSTState = getDSTState(date.tz(timezone));

      const isNotASummerDate = pipe(isSummerDate, not)(dateDSTState);
      const isInUTC = equals(
        getDestinationAndConfiguredTimezoneOffset('UTC'),
        0,
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
      comparing: dayjs.Dayjs,
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
      return date.tz(timezone).startOf('month') as dayjs.Dayjs;
    };

    public endOfMonth = (date: dayjs.Dayjs): dayjs.Dayjs => {
      return date.tz(timezone).endOf('month') as dayjs.Dayjs;
    };

    public isSameMonth = (
      date: dayjs.Dayjs,
      comparing: dayjs.Dayjs,
    ): boolean => {
      return date.tz(timezone).isSame(comparing.tz(timezone), 'month');
    };

    public getMonth = (date: dayjs.Dayjs): number => {
      console.log({ date, res: date.month(), tz: date.tz(timezone) });

      return date.month();
    };

    public getPreviousMonth = (date: dayjs.Dayjs): dayjs.Dayjs => {
      this.prevMonth = date.subtract(1, 'month');
      this.nextMonth = null;

      return date.subtract(1, 'month');
    };

    public addDays = (value: dayjs.Dayjs, count: number): dayjs.Dayjs => {
      if (this.prevMonth) {
        return count < 0
          ? this.prevMonth.subtract(Math.abs(count), 'day')
          : this.prevMonth.add(count, 'day');
      }
      if (this.nextMonth) {
        return count < 0
          ? this.nextMonth.subtract(Math.abs(count), 'day')
          : this.nextMonth.add(count, 'day');
      }

      return count < 0
        ? value.subtract(Math.abs(count), 'day')
        : value.add(count, 'day');
    };

    public getWeekdays = (): Array<string> => {
      if (this.prevMonth) {
        const start = this.prevMonth.startOf('week');

        return [0, 1, 2, 3, 4, 5, 6].map((diff) =>
          this.formatByString(start.add(diff, 'day'), 'dd'),
        );
      }
      if (this.nextMonth) {
        const start = this.nextMonth.startOf('week');

        return [0, 1, 2, 3, 4, 5, 6].map((diff) =>
          this.formatByString(start.add(diff, 'day'), 'dd'),
        );
      }

      const start = this.dayjs().startOf('week');

      return [0, 1, 2, 3, 4, 5, 6].map((diff) =>
        this.formatByString(start.add(diff, 'day'), 'dd'),
      );
    };

    public getWeekArray = (date: dayjs.Dayjs): Array<Array<dayjs.Dayjs>> => {
      const start = date.startOf('month').startOf('week');

      const end = date.endOf('month').endOf('week');

      let count = 0;
      let current = start;

      const nestedWeeks: Array<Array<dayjs.Dayjs>> = [];

      while (current.isBefore(end)) {
        const weekNumber = Math.floor(count / 7);
        nestedWeeks[weekNumber] = nestedWeeks[weekNumber] || [];
        nestedWeeks[weekNumber].push(current);

        current = current.add(1, 'day');
        count += 1;
      }

      return nestedWeeks;
    };

    public mergeDateAndTime = (
      date: dayjs.Dayjs,
      time: dayjs.Dayjs,
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
    getDestinationAndConfiguredTimezoneOffset,
  };
};

export default useDateTimePickerAdapter;
