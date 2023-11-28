import { ChangeEvent, useEffect, useMemo, useRef } from 'react';

import { useFormikContext } from 'formik';
import {
  difference,
  equals,
  has,
  includes,
  isEmpty,
  isNil,
  pluck,
  reject
} from 'ramda';

import { SelectEntry, useDeepCompare } from '@centreon/ui';

import { ConditionalOptions, Widget } from '../../../models';
import { getProperty } from '../utils';

interface UseCheckboxesProps {
  defaultValue: unknown | ConditionalOptions<Array<SelectEntry>>;
  options: Array<SelectEntry> | ConditionalOptions<Array<SelectEntry>>;
  propertyName: string;
}

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
  defaultValue
}: UseCheckboxesProps): UseCheckboxesState => {
  const previousDependencyValue = useRef<undefined | unknown>(undefined);

  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<Array<string> | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const dependencyValue = has('when', options)
    ? values.options[options.when]
    : undefined;

  const getOptions = (): Array<SelectEntry> => {
    if (has('when', options)) {
      return equals(dependencyValue, options.is)
        ? options.then
        : options.otherwise;
    }

    return options;
  };

  const optionsToDisplay = getOptions();

  const areAllOptionsSelected = isEmpty(
    difference(pluck('id', optionsToDisplay), value || [])
  );

  const selectAll = (): void => {
    setFieldValue(`options.${propertyName}`, pluck('id', optionsToDisplay));
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

    setFieldValue(
      `options.${propertyName}`,
      reject(equals(valueId), value || [])
    );
  };

  const isChecked = (id: string): boolean => includes(id, value || []);

  useEffect(
    () => {
      if (isNil(dependencyValue)) {
        return;
      }

      const canApplyDefaultValue = !!previousDependencyValue.current;

      if (!canApplyDefaultValue) {
        previousDependencyValue.current = dependencyValue;

        return;
      }

      const { is, then, otherwise } = defaultValue as ConditionalOptions<
        Array<SelectEntry>
      >;
      const defaultValueToApply = equals(is, dependencyValue)
        ? then
        : otherwise;

      setFieldValue(`options.${propertyName}`, defaultValueToApply);
    },
    useDeepCompare([dependencyValue])
  );

  return {
    areAllOptionsSelected,
    change,
    isChecked,
    optionsToDisplay,
    selectAll,
    unselectAll
  };
};
