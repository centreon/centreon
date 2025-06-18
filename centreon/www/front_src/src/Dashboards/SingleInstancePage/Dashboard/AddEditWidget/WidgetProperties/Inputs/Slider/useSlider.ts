import { useState } from 'react';

import { useFormikContext } from 'formik';

import { useDebounce } from '@centreon/ui';
import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

interface UseSliderState {
  changeInputValue: (newValue: number) => void;
  changeSliderValue: (e, newValue: number) => void;
  value?: number;
}

export const useSlider = ({
  propertyName
}: Pick<WidgetPropertyProps, 'propertyName'>): UseSliderState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const [value, setValue] = useState(
    getProperty<number>({ obj: values, propertyName }) || 0
  );

  const debounce = useDebounce({
    functionToDebounce: (newValue): void => {
      setFieldValue(`options.${propertyName}`, newValue);
    },
    wait: 100
  });

  const changeInputValue = (newValue: number): void => {
    setValue(newValue);
    debounce(newValue);
  };

  const changeSliderValue = (_, newValue: number): void => {
    setValue(newValue);
    debounce(newValue);
  };

  return {
    changeInputValue,
    changeSliderValue,
    value
  };
};
