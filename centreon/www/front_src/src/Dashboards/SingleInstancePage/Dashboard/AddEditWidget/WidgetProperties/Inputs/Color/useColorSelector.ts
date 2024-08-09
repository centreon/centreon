import { useCallback, useMemo, useState } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';

import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

import colors from './colors.json';

interface UseColorSelectorState {
  isColorSelected: (color: string) => boolean;
  isOpen: EventTarget | null;
  selectColor: (newColor: string) => void;
  toggle: (event: MouseEvent) => void;
  value?: string;
}

export const useColorSelector = ({
  propertyName
}: Pick<WidgetPropertyProps, 'propertyName'>): UseColorSelectorState => {
  const [isOpen, setIsOpen] = useState<EventTarget | null>(null);

  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const selectColor = useCallback(
    (newColor: string) => (event) => {
      setFieldValue(`options.${propertyName}`, newColor);
      toggle(event);
    },
    []
  );

  const isColorSelected = useCallback(
    (color: string) => equals(color, value ?? colors[0]),
    [value]
  );

  const toggle = useCallback(
    (event: MouseEvent) =>
      setIsOpen((previousIsOpen) =>
        previousIsOpen ? null : event.currentTarget
      ),
    []
  );

  return {
    isColorSelected,
    isOpen,
    selectColor,
    toggle,
    value
  };
};
