import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { map, pick, propEq, reject } from 'ramda';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import {
  Widget,
  WidgetDataResource,
  WidgetPropertyProps
} from '../../../models';
import { getProperty } from '../utils';

interface UseResourcesState {
  changeValue: (_, resource: SelectEntry) => void;
  changeValues: (_, resources: Array<SelectEntry>) => void;
  deleteItem: (_, option) => void;
  getEndpoint: (parameters) => string;
  value: Array<WidgetDataResource> | WidgetDataResource;
}

const useConnectedAutocomplete = ({
  propertyName,
  baseEndpoint
}: Pick<
  WidgetPropertyProps,
  'propertyName' | 'baseEndpoint'
>): UseResourcesState => {
  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const value = useMemo<Array<WidgetDataResource> | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const changeValues = (_, resources: Array<SelectEntry>): void => {
    const selectedResources = map(pick(['id', 'name']), resources || []);

    setFieldValue(`options.${propertyName}`, selectedResources);
    setFieldTouched(`options.${propertyName}`, true, false);
  };

  const changeValue = (_, resource: SelectEntry): void => {
    const selectedResource = resource ? pick(['id', 'name'], resource) : {};

    setFieldValue(`options.${propertyName}`, selectedResource);
    setFieldTouched(`options.${propertyName}`, true, false);
  };

  const deleteItem = (_, option): void => {
    const newValues = reject(propEq(option.id, 'id'), value);

    setFieldValue(`options.${propertyName}`, newValues);
    setFieldTouched(`options.${propertyName}`, true, false);
  };

  const getEndpoint = (parameters): string =>
    buildListingEndpoint({
      baseEndpoint,
      parameters
    });

  return {
    changeValue,
    changeValues,
    deleteItem,
    getEndpoint,
    value: value || []
  };
};

export default useConnectedAutocomplete;
