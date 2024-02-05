import { ChangeEvent, useMemo } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import {
  T,
  always,
  cond,
  equals,
  flatten,
  isEmpty,
  isNil,
  last,
  path,
  pluck,
  propEq,
  reject
} from 'ramda';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import { Dataset, ResourceTypeEnum } from '../../../models';
import {
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelMetaService,
  labelPleaseSelectAResource,
  labelService,
  labelServiceCategory,
  labelServiceGroup
} from '../../../translatedLabels';
import { baseEndpoint } from '../../../../api/endpoint';

type UseDatasetFilterState = {
  addResource: () => void;
  changeResource: (index: number) => (_, resource: SelectEntry) => void;
  changeResourceType: (
    index: number
  ) => (e: ChangeEvent<HTMLInputElement>) => void;
  changeResources: (
    index: number
  ) => (_, resources: Array<SelectEntry>) => void;
  deleteResource: (index: number) => () => void;
  deleteResourceItem: ({ index, option, resources }) => void;
  error: string | null;
  getResourceBaseEndpoint: (
    resourceType: ResourceTypeEnum
  ) => (parameters) => string;
  getResourceTypeOptions: (index: number) => Array<SelectEntry>;
  getSearchField: (resourceType: ResourceTypeEnum) => string;
  lowestResourceTypeReached: () => boolean;
};

const resourceTypeOptions = [
  {
    availableResourceTypeOptions: [
      { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup },
      { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
      { id: ResourceTypeEnum.Service, name: labelService }
    ],
    id: ResourceTypeEnum.Host,
    name: labelHost
  },
  {
    availableResourceTypeOptions: [
      { id: ResourceTypeEnum.HostGroup, name: labelHostGroup },
      { id: ResourceTypeEnum.Host, name: labelHost },
      { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup },
      { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
      { id: ResourceTypeEnum.Service, name: labelService }
    ],
    id: ResourceTypeEnum.HostCategory,
    name: labelHostCategory
  },
  {
    availableResourceTypeOptions: [
      { id: ResourceTypeEnum.HostCategory, name: labelHostCategory },
      { id: ResourceTypeEnum.Host, name: labelHost },
      { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup },
      { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
      { id: ResourceTypeEnum.Service, name: labelService }
    ],
    id: ResourceTypeEnum.HostGroup,
    name: labelHostGroup
  },
  {
    availableResourceTypeOptions: [],
    id: ResourceTypeEnum.MetaService,
    name: labelMetaService
  },
  {
    availableResourceTypeOptions: [],
    id: ResourceTypeEnum.Service,
    name: labelService
  },
  {
    availableResourceTypeOptions: [
      { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup },
      { id: ResourceTypeEnum.Service, name: labelService }
    ],
    id: ResourceTypeEnum.ServiceCategory,
    name: labelServiceCategory
  },
  {
    availableResourceTypeOptions: [
      { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
      { id: ResourceTypeEnum.Service, name: labelService }
    ],
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
    name: 'limit',
    value: 30
  }
];

const useDatasetFilter = (
  datasetFilter: Array<Dataset>,
  datasetFilterIndex: number
): UseDatasetFilterState => {
  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<FormikValues>();

  const value = useMemo<Array<Dataset> | undefined>(
    () =>
      path<Array<Dataset> | undefined>(
        ['datasetFilters', datasetFilterIndex],
        values
      ),
    [
      path<Array<Dataset> | undefined>(
        ['datasetFilters', datasetFilterIndex],
        values
      )
    ]
  );

  const lowestResourceTypeReached = (): boolean =>
    equals(last(datasetFilter)?.resourceType, ResourceTypeEnum.Service) ||
    equals(last(datasetFilter)?.resourceType, ResourceTypeEnum.MetaService);

  const getResourceTypeOptions = (index: number): Array<SelectEntry> => {
    if (isNil(value)) {
      return resourceTypeOptions;
    }

    const filteredResourceTypeOptions = flatten(
      pluck('availableResourceTypeOptions')(
        resourceTypeOptions.filter((option) =>
          equals(option.id, value[index - 1]?.resourceType)
        )
      )
    );

    return isEmpty(filteredResourceTypeOptions)
      ? resourceTypeOptions
      : filteredResourceTypeOptions;
  };

  const isTouched = useMemo<boolean | undefined>(
    () =>
      path<boolean | undefined>(
        ['datasetFilters', datasetFilterIndex],
        touched
      ),
    [path<boolean | undefined>(['datasetFilters', datasetFilterIndex], touched)]
  );

  const errorToDisplay =
    isTouched && isEmpty(datasetFilter) ? labelPleaseSelectAResource : null;

  const addResource = (): void => {
    setFieldValue(`datasetFilters.${datasetFilterIndex}`, [
      ...(datasetFilter || []),
      {
        resourceType: '',
        resources: []
      }
    ]);
  };

  const changeResource = (index: number) => (_, resource: SelectEntry) => {
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${index}.resources`,
      resource
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
  };

  const changeResources =
    (index: number) => (_, resources: Array<SelectEntry>) => {
      setFieldValue(
        `datasetFilters.${datasetFilterIndex}.${index}.resources`,
        resources
      );
      setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
    };

  const changeResourceType =
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      setFieldValue(
        `datasetFilters.${datasetFilterIndex}.${index}.resourceType`,
        e.target.value
      );
      setFieldValue(
        `datasetFilters.${datasetFilterIndex}.${index}.resources`,
        []
      );
    };

  const deleteResource = (index: number) => (): void => {
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}`,
      (datasetFilter || []).filter((_, i) => !equals(i, index))
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
  };

  const deleteResourceItem = ({ index, option, resources }): void => {
    const newResource = reject(propEq(option.id, 'id'), resources);

    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${index}.resources`,
      newResource
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
  };

  const getResourceBaseEndpoint =
    (resourceType: ResourceTypeEnum) =>
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

  const getSearchField = (resourceType: ResourceTypeEnum): string =>
    cond([
      [equals('host'), always('host.name')],
      [T, always('name')]
    ])(resourceType);

  return {
    addResource,
    changeResource,
    changeResourceType,
    changeResources,
    deleteResource,
    deleteResourceItem,
    error: errorToDisplay,
    getResourceBaseEndpoint,
    getResourceTypeOptions,
    getSearchField,
    lowestResourceTypeReached
  };
};

export default useDatasetFilter;
