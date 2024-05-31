import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { difference, equals, includes, isEmpty, pluck, reject } from 'ramda';

import { SelectEntry } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

interface UseCheckboxesState {
  areAllOptionsSelected: boolean;
  change: (event: ChangeEvent<HTMLInputElement>) => void;
  isChecked: (id: string) => boolean;
  optionsToDisplay: Array<SelectEntry>;
  selectAll: () => void;
  unselectAll: () => void;
}

export const useCheckboxes = ({
  propertyName,
  options,
  keepOneOptionSelected
}: Pick<
  WidgetPropertyProps,
  'propertyName' | 'defaultValue' | 'options' | 'keepOneOptionSelected'
>): UseCheckboxesState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<Array<string | number> | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const areAllOptionsSelected = isEmpty(
    difference(pluck('id', options || []), value || [])
  );

  const selectAll = (): void => {
    setFieldValue(`options.${propertyName}`, pluck('id', options || []));
  };
  const unselectAll = (): void => {
    setFieldValue(`options.${propertyName}`, []);
  };

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    const valueId = event.target.name;
    if (!includes(valueId, value || [])) {
      setFieldValue(`options.${propertyName}`, [...(value || []), valueId]);

      return;
    }

    const filteredOptions = reject(equals(valueId), value || []);

    if (keepOneOptionSelected && isEmpty(filteredOptions)) {
      return;
    }

    setFieldValue(`options.${propertyName}`, filteredOptions);
  };

  const isChecked = (id: string): boolean => includes(id, value || []);

  return {
    areAllOptionsSelected,
    change,
    isChecked,
    optionsToDisplay: options || [],
    selectAll,
    unselectAll
  };
};
