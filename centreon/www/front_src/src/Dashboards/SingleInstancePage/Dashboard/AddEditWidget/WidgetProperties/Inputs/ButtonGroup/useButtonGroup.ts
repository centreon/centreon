import { useCallback, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';

import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

interface UseButtonGroupState {
  isButtonSelected: (id) => boolean;
  selectOption: (id) => () => void;
  value?: string;
}

export const useButtonGroup = ({
  propertyName
}: Pick<WidgetPropertyProps, 'propertyName'>): UseButtonGroupState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const isButtonSelected = useCallback(
    (id): boolean => equals(value, id),
    [value]
  );

  const selectOption = useCallback(
    (id) => (): void => {
      setFieldValue(`options.${propertyName}`, id);
    },
    []
  );

  return {
    isButtonSelected,
    selectOption,
    value
  };
};
