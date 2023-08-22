import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { T, always, cond, equals, isEmpty } from 'ramda';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import {
  Widget,
  WidgetDataResource,
  WidgetResourceType
} from '../../../models';
import {
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelPleaseSelectAResource,
  labelService
} from '../../../../translatedLabels';
import { baseEndpoint } from '../../../../../../api/endpoint';
import { getDataProperty } from '../utils';

interface UseResourcesState {
  addResource: () => void;
  changeResourceType: (
    index: number
  ) => (e: ChangeEvent<HTMLInputElement>) => void;
  changeResources: (
    index: number
  ) => (_, resources: Array<SelectEntry>) => void;
  deleteResource: (index: number) => () => void;
  error: string | null;
  getResourceResourceBaseEndpoint: (
    resourceType: string
  ) => (parameters) => string;
  getSearchField: (resourceType: string) => string;
  resourceTypeOptions: Array<SelectEntry>;
  value: Array<WidgetDataResource>;
}

const resourceTypeOptions = [
  {
    id: WidgetResourceType.hostGroup,
    name: labelHostGroup
  },
  {
    id: WidgetResourceType.hostCategory,
    name: labelHostCategory
  },
  {
    id: WidgetResourceType.host,
    name: labelHost
  },
  {
    id: WidgetResourceType.service,
    name: labelService
  }
];

export const resourceTypeBaseEndpoints = {
  [WidgetResourceType.host]: '/hosts',
  [WidgetResourceType.hostCategory]: '/hosts/categories',
  [WidgetResourceType.hostGroup]: '/hostgroups',
  [WidgetResourceType.service]: '/resources'
};

const resourceQueryParameters = [
  {
    name: 'types',
    value: ['service']
  },
  {
    name: 'only_with_performance_data',
    value: true
  }
];

const useResources = (propertyName: string): UseResourcesState => {
  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<Widget>();

  const value = useMemo<Array<WidgetDataResource> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const isTouched = useMemo<boolean | undefined>(
    () => getDataProperty({ obj: touched, propertyName }),
    [getDataProperty({ obj: touched, propertyName })]
  );

  const errorToDisplay =
    isTouched && isEmpty(value) ? labelPleaseSelectAResource : null;

  const changeResourceType =
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      setFieldValue(
        `data.${propertyName}.${index}.resourceType`,
        e.target.value
      );
      setFieldValue(`data.${propertyName}.${index}.resources`, []);
    };

  const changeResources =
    (index: number) => (_, resources: Array<SelectEntry>) => {
      setFieldValue(`data.${propertyName}.${index}.resources`, resources);
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  const addResource = (): void => {
    setFieldValue(`data.${propertyName}`, [
      ...(value || []),
      {
        resourceType: '',
        resources: []
      }
    ]);
  };

  const deleteResource = (index: number | string) => (): void => {
    setFieldValue(
      `data.${propertyName}`,
      (value || []).filter((_, i) => !equals(i, index))
    );
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const getResourceResourceBaseEndpoint =
    (resourceType: string) =>
    (parameters): string => {
      return buildListingEndpoint({
        baseEndpoint: `${baseEndpoint}/monitoring${resourceTypeBaseEndpoints[resourceType]}`,
        customQueryParameters: equals(resourceType, WidgetResourceType.service)
          ? resourceQueryParameters
          : undefined,
        parameters
      });
    };

  const getSearchField = (resourceType: string): string =>
    cond([
      [equals('host'), always('host.name')],
      [T, always('name')]
    ])(resourceType);

  return {
    addResource,
    changeResourceType,
    changeResources,
    deleteResource,
    error: errorToDisplay,
    getResourceResourceBaseEndpoint,
    getSearchField,
    resourceTypeOptions,
    value: value || []
  };
};

export default useResources;
