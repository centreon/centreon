import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';

import { equals } from 'ramda';
import { Widget } from '../../../models';
import { getProperty } from '../utils';

interface TopBottomSettingsValue {
  numberOfValues: number;
  order: 'top' | 'bottom';
  showLabels: boolean;
}

interface UseTopBottomSettingsState {
  changeNumberOfValues: (event: ChangeEvent<HTMLInputElement>) => void;
  changeOrder: (_, newOrder: string) => void;
  value: TopBottomSettingsValue;
}

const useTopBottomSettings = (
  propertyName: string
): UseTopBottomSettingsState => {
  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const value = useMemo<TopBottomSettingsValue | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as TopBottomSettingsValue;

  const changeNumberOfValues = (event: ChangeEvent<HTMLInputElement>): void => {
    const value = equals(Number(event.target.value), 0)
      ? 1
      : Number(event.target.value);

    setFieldValue(`options.${propertyName}.numberOfValues`, value);
    setFieldTouched(`options.${propertyName}.numberOfValues`, true, false);
  };

  const changeOrder = (_, newOrder: string): void => {
    setFieldValue(`options.${propertyName}.order`, newOrder);
  };

  return {
    changeNumberOfValues,
    changeOrder,
    value
  };
};

export default useTopBottomSettings;
