import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';

import { SelectEntry } from '@centreon/ui';

import {
  labelLast12Months,
  labelLast7Days,
  labelLast24Hours,
  labelLast30Days,
  labelLast3Months,
  labelLast6Months,
  labelLastHour
} from '../../../../translatedLabels';
import { Widget } from '../../../models';
import { getProperty } from '../utils';

const options: Array<SelectEntry> = [
  {
    id: 1,
    name: labelLastHour
  },
  {
    id: 24,
    name: labelLast24Hours
  },
  {
    id: 7 * 24,
    name: labelLast7Days
  },
  {
    id: 30 * 24,
    name: labelLast30Days
  },
  {
    id: 3 * 30 * 24,
    name: labelLast3Months
  },
  {
    id: 6 * 30 * 24,
    name: labelLast6Months
  },
  {
    id: 12 * 30 * 24,
    name: labelLast12Months
  }
];

interface TimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

interface UseTimePeriodState {
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
    setFieldValue(`options.${propertyName}`, {
      timePeriodType: Number(e.target.value)
    });
  };

  useEffect(() => {
    if (value.timePeriodType) {
      return;
    }

    setFieldValue(`options.${propertyName}`, {
      timePeriodType: options[0].id as number
    });
  }, []);

  return {
    options,
    setTimePeriod,
    value
  };
};

export default useTimePeriod;
