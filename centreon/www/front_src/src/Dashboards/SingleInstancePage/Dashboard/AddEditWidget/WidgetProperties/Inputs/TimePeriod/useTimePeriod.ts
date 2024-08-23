import { ChangeEvent, useEffect, useMemo } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { equals } from 'ramda';

import { SelectEntry } from '@centreon/ui';

import {
  labelCustomize,
  labelLast3Hours,
  labelLast3Months,
  labelLast6Hours,
  labelLast6Months,
  labelLast7Days,
  labelLast12Hours,
  labelLast12Months,
  labelLast24Hours,
  labelLast30Days,
  labelLastHour
} from '../../../../translatedLabels';
import { Widget } from '../../../models';
import { getProperty } from '../utils';

const day = 24;
const month = 30;

export const options: Array<SelectEntry> = [
  {
    id: 1,
    name: labelLastHour
  },
  {
    id: day / 8,
    name: labelLast3Hours
  },
  {
    id: day / 4,
    name: labelLast6Hours
  },
  {
    id: day / 2,
    name: labelLast12Hours
  },
  {
    id: day,
    name: labelLast24Hours
  },
  {
    id: 7 * day,
    name: labelLast7Days
  },
  {
    id: month * day,
    name: labelLast30Days
  },
  {
    id: 3 * month * day,
    name: labelLast3Months
  },
  {
    id: 6 * month * day,
    name: labelLast6Months
  },
  {
    id: 12 * month * day,
    name: labelLast12Months
  },
  {
    id: -1,
    name: labelCustomize
  }
];

interface TimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

interface UseTimePeriodState {
  changeCustomDate: (property: string) => (newDate: Date) => void;
  isCustomizeTimePeriod: boolean;
  options: Array<SelectEntry>;
  setTimePeriod: (e: ChangeEvent<HTMLInputElement>) => void;
  value: TimePeriod;
}

const useTimePeriod = (propertyName: string): UseTimePeriodState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<TimePeriod | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as TimePeriod;

  const setTimePeriod = (e: ChangeEvent<HTMLInputElement>): void => {
    const newType = Number(e.target.value);

    if (equals(newType, -1)) {
      setFieldValue(`options.${propertyName}`, {
        end: dayjs(),
        start: dayjs().subtract(1, 'hour'),
        timePeriodType: newType
      });

      return;
    }

    setFieldValue(`options.${propertyName}`, {
      ...value,
      timePeriodType: newType
    });
  };

  const changeCustomDate = (property: string) => (newDate: Date) => {
    setFieldValue(`options.${propertyName}`, {
      ...value,
      [property]: newDate.toISOString()
    });
  };

  useEffect(() => {
    if (value.timePeriodType) {
      return;
    }

    setFieldValue(`options.${propertyName}`, {
      ...value,
      timePeriodType: options[0].id as number
    });
  }, []);

  const isCustomizeTimePeriod = equals(value.timePeriodType, -1);

  return {
    changeCustomDate,
    isCustomizeTimePeriod,
    options,
    setTimePeriod,
    value
  };
};

export default useTimePeriod;
