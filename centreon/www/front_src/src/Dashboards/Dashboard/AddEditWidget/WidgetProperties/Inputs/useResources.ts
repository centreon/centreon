import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { T, always, cond, equals } from 'ramda';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import { Widget, WidgetDataResource } from '../../models';
import {
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelService
} from '../../../translatedLabels';
import { baseEndpoint } from '../../../../../api/endpoint';

import { getDataProperty } from './utils';

interface UseResourcesState {
  addResource: () => void;
  changeResourceType: (
    index: number
  ) => (e: ChangeEvent<HTMLInputElement>) => void;
  changeResources: (
    index: number
  ) => (_, resources: Array<SelectEntry>) => void;
  deleteResource: (index: number) => () => void;
  getResourceResourceBaseEndpoint: (
    resourceType: string
  ) => (parameters) => string;
  getSearchField: (resourceType: string) => string;
  resourceTypeOptions: Array<SelectEntry>;
  value: Array<WidgetDataResource>;
}

const resourceTypeOptions = [
  {
    id: 'host-group',
    name: labelHostGroup
  },
  {
    id: 'host-category',
    name: labelHostCategory
  },
  {
    id: 'host',
    name: labelHost
  },
  {
    id: 'service',
    name: labelService
  }
];

const resourceTypeBaseEndpoints = {
  host: '/hosts',
  'host-category': '/hosts/categories',
  'host-group': '/hostgroups',
  service: '/services'
};

const useResources = (propertyName: string): UseResourcesState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<Array<WidgetDataResource> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

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
  };

  const getResourceResourceBaseEndpoint =
    (resourceType: string) =>
    (parameters): string => {
      return buildListingEndpoint({
        baseEndpoint: `${baseEndpoint}/monitoring${resourceTypeBaseEndpoints[resourceType]}`,
        parameters
      });
    };

  const getSearchField = (resourceType: string): string =>
    cond([
      [equals('host'), always('host.name')],
      [equals('service'), always('service.display_name')],
      [T, always('name')]
    ])(resourceType);

  return {
    addResource,
    changeResourceType,
    changeResources,
    deleteResource,
    getResourceResourceBaseEndpoint,
    getSearchField,
    resourceTypeOptions,
    value: value || []
  };
};

export default useResources;
