import { ChangeEvent, useMemo } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import {
  T,
  always,
  cond,
  equals,
  isEmpty,
  isNil,
  last,
  path,
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
        ['data', 'datasetFilters', datasetFilterIndex],
        values
      ),
    [
      path<Array<Dataset> | undefined>(
        ['data', 'datasetFilters', datasetFilterIndex],
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

    if (equals(value[index - 1]?.resourceType, ResourceTypeEnum.HostCategory)) {
      return [
        { id: ResourceTypeEnum.Host, name: labelHost },
        { id: ResourceTypeEnum.HostGroup, name: labelHostGroup },
        { id: ResourceTypeEnum.Service, name: labelService },
        { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
        { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup }
      ];
    }

    if (equals(value[index - 1]?.resourceType, ResourceTypeEnum.HostGroup)) {
      return [
        { id: ResourceTypeEnum.Host, name: labelHost },
        { id: ResourceTypeEnum.HostCategory, name: labelHostCategory },
        { id: ResourceTypeEnum.Service, name: labelService },
        { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
        { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup }
      ];
    }

    if (equals(value[index - 1]?.resourceType, ResourceTypeEnum.Host)) {
      return [
        { id: ResourceTypeEnum.Service, name: labelService },
        { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory },
        { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup }
      ];
    }

    if (
      equals(value[index - 1]?.resourceType, ResourceTypeEnum.ServiceCategory)
    ) {
      return [
        { id: ResourceTypeEnum.Service, name: labelService },
        { id: ResourceTypeEnum.ServiceGroup, name: labelServiceGroup }
      ];
    }

    if (equals(value[index - 1]?.resourceType, ResourceTypeEnum.ServiceGroup)) {
      return [
        { id: ResourceTypeEnum.Service, name: labelService },
        { id: ResourceTypeEnum.ServiceCategory, name: labelServiceCategory }
      ];
    }

    if (
      equals(value[index - 1]?.resourceType, ResourceTypeEnum.MetaService) ||
      equals(value[index - 1]?.resourceType, ResourceTypeEnum.Service)
    ) {
      return [];
    }

    return resourceTypeOptions;
  };

  const isTouched = useMemo<boolean | undefined>(
    () =>
      path<boolean | undefined>(
        ['data', 'datasetFilters', datasetFilterIndex],
        touched
      ),
    [
      path<boolean | undefined>(
        ['data', 'datasetFilters', datasetFilterIndex],
        touched
      )
    ]
  );

  const errorToDisplay =
    isTouched && isEmpty(datasetFilter) ? labelPleaseSelectAResource : null;

  const addResource = (): void => {
    setFieldValue(`data.datasetFilters.${datasetFilterIndex}`, [
      ...(datasetFilter || []),
      {
        resourceType: '',
        resources: []
      }
    ]);
  };

  const changeResource = (index: number) => (_, resource: SelectEntry) => {
    setFieldValue(
      `data.datasetFilters.${datasetFilterIndex}.${index}.resources`,
      [resource]
    );
    setFieldTouched(`data.datasetFilters.${datasetFilterIndex}`, true, false);
  };

  const changeResources =
    (index: number) => (_, resources: Array<SelectEntry>) => {
      setFieldValue(
        `data.datasetFilters.${datasetFilterIndex}.${index}.resources`,
        resources
      );
      setFieldTouched(`data.datasetFilters.${datasetFilterIndex}`, true, false);
    };

  const changeResourceType =
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      setFieldValue(
        `data.datasetFilters.${datasetFilterIndex}.${index}.resourceType`,
        e.target.value
      );
      setFieldValue(
        `data.datasetFilters.${datasetFilterIndex}.${index}.resources`,
        []
      );
    };

  const deleteResource = (index: number) => (): void => {
    setFieldValue(
      `data.datasetFilters.${datasetFilterIndex}`,
      (datasetFilter || []).filter((_, i) => !equals(i, index))
    );
    setFieldTouched(`data.datasetFilters.${datasetFilterIndex}`, true, false);
  };

  const deleteResourceItem = ({ index, option, resources }): void => {
    const newResource = reject(propEq(option.id, 'id'), resources);

    setFieldValue(
      `data.datasetFilters.${datasetFilterIndex}.${index}.resources`,
      newResource
    );
    setFieldTouched(`data.dataset.${datasetFilterIndex}`, true, false);
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
