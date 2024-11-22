import { useCallback, useMemo } from 'react';

import dayjs, { Dayjs } from 'dayjs';
import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';

import { SelectEntry } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  localeInputKeyDerivedAtom,
  timezoneInputKeyDerivedAtom
} from '../../../atoms';
import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

interface UseDatePickerState {
  changeDate: ({ date }) => void;
  currentDate: Dayjs;
  locale: string;
  maxDate: Dayjs;
  timezone: string;
}

export const useDatePicker = ({
  propertyName,
  datePicker
}: Pick<
  WidgetPropertyProps,
  'propertyName' | 'datePicker'
>): UseDatePickerState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const localeKey = useAtomValue(localeInputKeyDerivedAtom);
  const timezoneKey = useAtomValue(timezoneInputKeyDerivedAtom);
  const user = useAtomValue(userAtom);

  const value = useMemo<number | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const formLocale = localeKey && (values.options[localeKey] as SelectEntry);
  const formTimezone =
    timezoneKey && (values.options[timezoneKey] as SelectEntry);

  const locale = useMemo(
    () => formLocale?.id || user.locale,
    [user, formLocale]
  );
  const timezone = useMemo(
    () => formTimezone?.id || user.timezone,
    [formTimezone, user]
  );

  const firstMountDate = useMemo(() => new Date().getTime(), []);

  const currentDate = useMemo(
    () =>
      dayjs(value ?? firstMountDate)
        .locale(locale)
        .tz(timezone),
    [value, locale, timezone]
  );

  const maxDate = useMemo(
    () =>
      dayjs()
        .tz(timezone)
        .add(datePicker?.maxDays || 0, 'day'),
    [timezone]
  );

  const changeDate = useCallback(
    ({ date }: { date: Date }) => {
      setFieldValue(`options.${propertyName}`, date.getTime());
    },
    [propertyName]
  );

  return {
    changeDate,
    currentDate,
    locale,
    maxDate,
    timezone
  };
};
