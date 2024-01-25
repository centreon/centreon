import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  T,
  always,
  cond,
  equals,
  isEmpty,
  propEq,
  reject,
  pluck,
  includes
} from 'ramda';

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
  labelService,
  labelServiceCategory,
  labelServiceGroup
} from '../../../../translatedLabels';
import { baseEndpoint } from '../../../../../../api/endpoint';
import { getDataProperty } from '../utils';

interface UseResourcesState {
  addButtonHidden?: boolean;
  addResource: () => void;
  changeResource: (index: number) => (_, resources: SelectEntry) => void;
  changeResourceType: (
    index: number
  ) => (e: ChangeEvent<HTMLInputElement>) => void;
  changeResources: (
    index: number
  ) => (_, resources: Array<SelectEntry>) => void;
  deleteResource: (index: number) => () => void;
  deleteResourceItem: ({ index, option, resources }) => void;
  error: string | null;
  getResourceResourceBaseEndpoint: (
    resourceType: string
  ) => (parameters) => string;
  getResourceTypeOptions: (resource) => Array<ResourceTypeOption>;
  getSearchField: (resourceType: string) => string;
  value: Array<WidgetDataResource>;
}

interface ResourceTypeOption {
  id: WidgetResourceType;
  name: string;
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
    id: WidgetResourceType.serviceGroup,
    name: labelServiceGroup
  },
  {
    id: WidgetResourceType.serviceCategory,
    name: labelServiceCategory
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
  [WidgetResourceType.service]: '/resources',
  [WidgetResourceType.serviceCategory]: '/services/categories',
  [WidgetResourceType.serviceGroup]: '/servicegroups'
};

const resourceQueryParameters = [
  {
    name: 'types',
    value: ['service']
  },
  {
    name: 'only_with_performance_data',
    value: true
  },
  {
    name: 'limit',
    value: 30
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

  const changeResource = (index: number) => (_, resource: SelectEntry) => {
    setFieldValue(`data.${propertyName}.${index}.resources`, [resource]);
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

  const deleteResourceItem = ({ index, option, resources }): void => {
    const newResource = reject(propEq('id', option.id), resources);

    setFieldValue(`data.${propertyName}.${index}.resources`, newResource);
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
        parameters: {
          ...parameters,
          limit: 30
        }
      });
    };

  const getSearchField = (resourceType: string): string =>
    cond([
      [equals('host'), always('host.name')],
      [T, always('name')]
    ])(resourceType);

  const getResourceTypeOptions = (resource): Array<ResourceTypeOption> => {
    const resourcetypesIds = pluck('resourceType', value || []);

    const newResourceTypeOptions = reject(
      ({ id }) =>
        !equals(id, resource.resourceType) && includes(id, resourcetypesIds),
      resourceTypeOptions
    );

    return newResourceTypeOptions;
  };

  useEffect(() => {
    if (!isEmpty(value)) {
      return;
    }

    setFieldValue(`data.${propertyName}`, [
      {
        resourceType: '',
        resources: []
      }
    ]);
  }, [values.moduleName]);

  return {
    addResource,
    changeResource,
    changeResourceType,
    changeResources,
    deleteResource,
    deleteResourceItem,
    error: errorToDisplay,
    getResourceResourceBaseEndpoint,
    getResourceTypeOptions,
    getSearchField,
    value: value || []
  };
};

export default useResources;
