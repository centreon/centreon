import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';

import { Widget } from '../../../models';
import { getProperty } from '../utils';

interface UseTimePeriodState {
  setSelect: (e: ChangeEvent<HTMLInputElement>) => void;
  value: string | number;
}

const useSelect = (propertyName: string): UseTimePeriodState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<string | number | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as string | number;

  const setSelect = (e: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(`options.${propertyName}`, e.target.value);
  };

  return {
    setSelect,
    value
  };
};

export default useSelect;
