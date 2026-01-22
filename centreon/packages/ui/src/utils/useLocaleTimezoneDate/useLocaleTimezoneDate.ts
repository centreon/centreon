import type { Dayjs } from 'dayjs';
import { isNil } from 'ramda';
import { useCallback } from 'react';

export interface Props {
  date: Dayjs;
  format?: string;
  locale?: string;
  timezone?: string;
}

interface UseLocaleTimeZoneDate {
  formatDate: (props: Props) => string;
  toLocaleTimezoneDate: (props: Omit<Props, 'format'>) => Dayjs;
}

export const useLocaleTimezoneDate = (): UseLocaleTimeZoneDate => {
  const toLocaleTimezoneDate = useCallback(
    ({ date, locale = 'en', timezone }: Omit<Props, 'format'>): Dayjs => {
      if (isNil(timezone)) {
        return date.locale(locale);
      }

      return date.locale(locale).tz(timezone);
    },
    []
  );

  const formatDate = useCallback(
    ({ date, locale = 'en', timezone, format = 'L LT' }: Props): string => {
      return toLocaleTimezoneDate({ date, locale, timezone }).format(format);
    },
    [toLocaleTimezoneDate]
  );

  return {
    formatDate,
    toLocaleTimezoneDate
  };
};
