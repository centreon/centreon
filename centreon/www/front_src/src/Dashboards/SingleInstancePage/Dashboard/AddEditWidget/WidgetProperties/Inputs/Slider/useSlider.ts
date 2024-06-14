import { useMemo } from 'react';

import { useFormikContext } from 'formik';

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

  const value = useMemo<number | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const changeInputValue = (newValue: number): void => {
    setFieldValue(`options.${propertyName}`, newValue);
  };

  const changeSliderValue = (_, newValue: number): void => {
    setFieldValue(`options.${propertyName}`, newValue);
  };

  return {
    changeInputValue,
    changeSliderValue,
    value
  };
};
