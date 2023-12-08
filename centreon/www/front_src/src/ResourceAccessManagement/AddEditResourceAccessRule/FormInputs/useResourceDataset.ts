import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  T,
  always,
  cond,
  equals,
  isEmpty,
  path,
  propEq,
  reject,
  split
} from 'ramda';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import { Dataset, ResourceAccessRule, ResourceTypeEnum } from '../../models';
import {
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelMetaService,
  labelPleaseSelectAResource,
  labelService,
  labelServiceCategory,
  labelServiceGroup
} from '../../translatedLabels';

import { baseEndpoint } from 'www/front_src/src/api/endpoint';

interface UseResourceDatasetState {
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
  getResourceBaseEndpoint: (resourceType: string) => (parameters) => string;
  getSearchField: (resourceType: string) => string;
  resourceTypeOptions: Array<SelectEntry>;
  value: Array<Dataset>;
}

const resourceTypeOptions = [
  {
    id: ResourceTypeEnum.Host,
    name: labelHost
  },
  {
    id: ResourceTypeEnum.HostCategory,
    name: labelHostCategory
  },
  {
    id: ResourceTypeEnum.HostGroup,
    name: labelHostGroup
  },
  {
    id: ResourceTypeEnum.MetaService,
    name: labelMetaService
  },
  {
    id: ResourceTypeEnum.Service,
    name: labelService
  },
  {
    id: ResourceTypeEnum.ServiceCategory,
    name: labelServiceCategory
  },
  {
    id: ResourceTypeEnum.ServiceGroup,
    name: labelServiceGroup
  }
];

export const resourceTypeBaseEndpoints = {
  [ResourceTypeEnum.Host]: '/configuration/hosts',
  [ResourceTypeEnum.HostCategory]: '/configuration/hosts/categories',
  [ResourceTypeEnum.HostGroup]: '/configuration/hosts/groups',
  [ResourceTypeEnum.MetaService]: '/configuration/metaservices',
  [ResourceTypeEnum.Service]: '/configuration/services',
  [ResourceTypeEnum.ServiceCategory]: '/configuration/services/categories',
  [ResourceTypeEnum.ServiceGroup]: '/configuration/services/groups'
};

const resourceQueryParameters = [
  {
    name: 'types',
    value: ['service']
  },
  {
    name: 'limit',
    value: 30
  }
];

const getDataProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['data', ...split('.', propertyName)], obj);

const useResourceDataset = (propertyName: string): UseResourceDatasetState => {
  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<ResourceAccessRule>();

  const value = useMemo<Array<Dataset> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const isTouched = useMemo<boolean | undefined>(
    () => getDataProperty({ obj: touched, propertyName }),
    [getDataProperty({ obj: touched, propertyName })]
  );

  const errorToDisplay =
    isTouched && isEmpty(value) ? labelPleaseSelectAResource : null;

  const addResource = (): void => {
    setFieldValue(`data.${propertyName}`, [
      ...(value || []),
      {
        resourceType: '',
        resources: []
      }
    ]);
  };

  const changeResource = (index: number) => (_, resource: SelectEntry) => {
    setFieldValue(`data.${propertyName}.${index}.resources`, [resource]);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const changeResources =
    (index: number) => (_, resources: Array<SelectEntry>) => {
      setFieldValue(`data.${propertyName}.${index}.resources`, resources);
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  const changeResourceType =
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      setFieldValue(
        `data.${propertyName}.${index}.resourceType`,
        e.target.value
      );
      setFieldValue(`data.${propertyName}.${index}.resources`, []);
    };

  const deleteResource = (index: number | string) => (): void => {
    setFieldValue(
      `data.${propertyName}`,
      (value || []).filter((_, i) => !equals(i, index))
    );
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const deleteResourceItem = ({ index, option, resources }): void => {
    const newResource = reject(propEq(option.id, 'id'), resources);

    setFieldValue(`data.${propertyName}.${index}.resources`, newResource);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const getResourceBaseEndpoint =
    (resourceType: string) =>
    (parameters): string => {
      return buildListingEndpoint({
        baseEndpoint: `${baseEndpoint}${resourceTypeBaseEndpoints[resourceType]}`,
        customQueryParameters: equals(resourceType, ResourceTypeEnum.Service)
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
  }, []);

  return {
    addResource,
    changeResource,
    changeResourceType,
    changeResources,
    deleteResource,
    deleteResourceItem,
    error: errorToDisplay,
    getResourceBaseEndpoint,
    getSearchField,
    resourceTypeOptions,
    value: value || []
  };
};

export default useResourceDataset;
