import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import {
  path,
  T,
  always,
  cond,
  equals,
  flatten,
  has,
  includes,
  isEmpty,
  isNil,
  last,
  pluck,
  propEq,
  reject
} from 'ramda';

import {
  QueryParameter,
  SelectEntry,
  buildListingEndpoint
} from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { baseEndpoint } from '../../../../api/endpoint';
import { selectedDatasetFiltersAtom } from '../../../atom';
import { Dataset, ResourceAccessRule, ResourceTypeEnum } from '../../../models';
import {
  labelAllBusinessViewsSelected,
  labelAllHostGroupsSelected,
  labelAllHostsSelected,
  labelAllResources,
  labelAllResourcesSelected,
  labelAllServiceGroupsSelected,
  labelBusinessView,
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelMetaService,
  labelPleaseSelectAResource,
  labelSelectResource,
  labelService,
  labelServiceCategory,
  labelServiceGroup
} from '../../../translatedLabels';

type UseDatasetFilterState = {
  addResource: () => void;
  changeResource: (index: number) => (_, resource: SelectEntry) => void;
  changeResourceType: (
    index: number
  ) => (e: ChangeEvent<HTMLInputElement>) => void;
  changeResources: (
    index: number
  ) => (_, resources: Array<SelectEntry>) => void;
  deleteButtonHidden: boolean;
  deleteResource: (index: number) => () => void;
  deleteResourceItem: ({ index, option, resources }) => void;
  displayAllOfResourceTypeCheckbox: (resourceType: ResourceTypeEnum) => boolean;
  error: string | null;
  getLabelForSelectedResources: (index: number) => string;
  getResourceBaseEndpoint: (
    index: number,
    resourceType: ResourceTypeEnum
  ) => (parameters) => string;
  getResourceTypeOptions: (index: number) => Array<SelectEntry>;
  getSearchField: (resourceType: ResourceTypeEnum) => string;
  lowestResourceTypeReached: () => boolean;
};

const resourceTypeOptions = [
  {
    availableResourceTypeOptions: [],
    id: ResourceTypeEnum.All,
    name: labelAllResources
  },
  {
    availableResourceTypeOptions: [],
    id: ResourceTypeEnum.BusinessView,
    name: labelBusinessView
  },
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
  [ResourceTypeEnum.BusinessView]: '/configuration/business-views',
  [ResourceTypeEnum.Host]: '/configuration/hosts',
  [ResourceTypeEnum.HostCategory]: '/configuration/hosts/categories',
  [ResourceTypeEnum.HostGroup]: '/configuration/hosts/groups',
  [ResourceTypeEnum.MetaService]: '/configuration/metaservices',
  [ResourceTypeEnum.Service]: '/configuration/services',
  [ResourceTypeEnum.ServiceCategory]: '/configuration/services/categories',
  [ResourceTypeEnum.ServiceGroup]: '/configuration/services/groups'
};

const searchParametersBySelectedResourceType = {
  [ResourceTypeEnum.HostGroup]: {
    [ResourceTypeEnum.HostCategory]: 'hostcategory.id'
  },
  [ResourceTypeEnum.HostCategory]: {
    [ResourceTypeEnum.HostGroup]: 'hostgroup.id'
  },
  [ResourceTypeEnum.Host]: {
    [ResourceTypeEnum.HostGroup]: 'group.id',
    [ResourceTypeEnum.HostCategory]: 'category.id'
  },
  [ResourceTypeEnum.ServiceGroup]: {
    [ResourceTypeEnum.HostGroup]: 'hostgroup.id',
    [ResourceTypeEnum.HostCategory]: 'hostcategory.id',
    [ResourceTypeEnum.Host]: 'host.id',
    [ResourceTypeEnum.ServiceCategory]: 'category.id'
  },
  [ResourceTypeEnum.ServiceCategory]: {
    [ResourceTypeEnum.HostGroup]: 'hostgroup.id',
    [ResourceTypeEnum.HostCategory]: 'hostcategory.id',
    [ResourceTypeEnum.Host]: 'host.id',
    [ResourceTypeEnum.ServiceGroup]: 'group.id'
  },
  [ResourceTypeEnum.Service]: {
    [ResourceTypeEnum.HostGroup]: 'hostgroup.id',
    [ResourceTypeEnum.HostCategory]: 'hostcategory.id',
    [ResourceTypeEnum.Host]: 'host.id',
    [ResourceTypeEnum.ServiceGroup]: 'group.id',
    [ResourceTypeEnum.ServiceCategory]: 'category.id'
  }
};

const labelsForSelectedResources = {
  [ResourceTypeEnum.Host]: labelAllHostsSelected,
  [ResourceTypeEnum.HostGroup]: labelAllHostGroupsSelected,
  [ResourceTypeEnum.ServiceGroup]: labelAllServiceGroupsSelected,
  [ResourceTypeEnum.BusinessView]: labelAllBusinessViewsSelected
};

const useDatasetFilter = (
  datasetFilter: Array<Dataset>,
  datasetFilterIndex: number
): UseDatasetFilterState => {
  const [selectedDatasetFilters, setSelectedDatasetFiltes] = useAtom(
    selectedDatasetFiltersAtom
  );

  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<ResourceAccessRule>();

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

  const platform = useAtomValue(platformVersionsAtom);
  const isBamInstalled = has('centreon-bam-server', platform?.modules);

  const lowestResourceTypeReached = (): boolean =>
    equals(last(datasetFilter)?.resourceType, ResourceTypeEnum.Service) ||
    equals(last(datasetFilter)?.resourceType, ResourceTypeEnum.MetaService) ||
    equals(last(datasetFilter)?.resourceType, ResourceTypeEnum.BusinessView);

  const getResourceTypeOptions = (index: number): Array<SelectEntry> => {
    const prefilteredResourceTypeOptions = isBamInstalled
      ? resourceTypeOptions
      : resourceTypeOptions.filter(
          (option) => !equals(option.id, ResourceTypeEnum.BusinessView)
        );

    if (isNil(value)) {
      return prefilteredResourceTypeOptions;
    }

    const filteredResourceTypeOptions = flatten(
      pluck('availableResourceTypeOptions')(
        prefilteredResourceTypeOptions.filter((option) =>
          equals(option.id, value[index - 1]?.resourceType)
        )
      )
    );

    const selectedResourceTypes = isNil(
      selectedDatasetFilters[datasetFilterIndex]
    )
      ? []
      : pluck('type', selectedDatasetFilters[datasetFilterIndex]);

    const remainingResourceTypeOptions = reject(
      (type: { id: ResourceTypeEnum; name: string }) =>
        !equals(type.id, value[index].resourceType) &&
        includes(type.id, selectedResourceTypes),
      filteredResourceTypeOptions
    );

    return isEmpty(remainingResourceTypeOptions)
      ? prefilteredResourceTypeOptions
      : remainingResourceTypeOptions;
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

  const deleteButtonHidden = datasetFilter.length <= 1;

  const displayAllOfResourceTypeCheckbox = (
    resourceType: ResourceTypeEnum
  ): boolean =>
    equals(resourceType, ResourceTypeEnum.HostGroup) ||
    equals(resourceType, ResourceTypeEnum.Host) ||
    equals(resourceType, ResourceTypeEnum.ServiceGroup) ||
    equals(resourceType, ResourceTypeEnum.BusinessView);

  const getLabelForSelectedResources = (index: number): string => {
    if (datasetFilter[index]?.allOfResourceType) {
      return labelsForSelectedResources[datasetFilter[index].resourceType];
    }

    if (equals(datasetFilter[index].resourceType, ResourceTypeEnum.All)) {
      return labelAllResourcesSelected;
    }

    return labelSelectResource;
  };

  const addResource = (): void => {
    setFieldValue(`datasetFilters.${datasetFilterIndex}`, [
      ...(datasetFilter || []),
      {
        allOfResourceType: false,
        resourceType: ResourceTypeEnum.Empty,
        resources: []
      }
    ]);

    setSelectedDatasetFiltes(
      selectedDatasetFilters.map((datasets, indexFilter) => {
        if (equals(indexFilter, datasetFilterIndex)) {
          return [
            ...selectedDatasetFilters[indexFilter],
            {
              allOfResourceType: false,
              ids: [],
              type: ResourceTypeEnum.Empty
            }
          ];
        }

        return datasets;
      })
    );
  };

  const changeResource = (index: number) => (_, resource: SelectEntry) => {
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${index}.resources`,
      resource
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
    setSelectedDatasetFiltes(
      selectedDatasetFilters.map((datasets, indexFilter) => {
        if (equals(indexFilter, datasetFilterIndex)) {
          return selectedDatasetFilters[indexFilter].map((dataset, i) => {
            if (equals(i, index)) {
              return {
                allOfResourceType: false,
                ids: [...dataset.ids, resource.id as number],
                type: dataset.type
              };
            }

            return dataset;
          });
        }

        return datasets;
      })
    );
  };

  const changeResources =
    (index: number) => (_, resources: Array<SelectEntry>) => {
      setFieldValue(
        `datasetFilters.${datasetFilterIndex}.${index}.resources`,
        resources
      );
      setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
      setSelectedDatasetFiltes(
        selectedDatasetFilters.map((datasets, indexFilter) => {
          if (equals(indexFilter, datasetFilterIndex)) {
            return selectedDatasetFilters[indexFilter].map((dataset, i) => {
              if (equals(i, index)) {
                return {
                  allOfResourceType: false,
                  ids: pluck('id', resources) as Array<number>,
                  type: dataset.type
                };
              }

              return dataset;
            });
          }

          return datasets;
        })
      );
    };

  const changeResourceType =
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      setFieldValue(
        `datasetFilters.${datasetFilterIndex}`,
        value
          ?.map((dataset, i) => {
            if (index < i && !equals(e.target.value, dataset.resourceType)) {
              return undefined;
            }

            if (equals(i, index)) {
              return {
                allOfResourceType: false,
                resourceType: e.target.value,
                resources: []
              };
            }

            return dataset;
          })
          .filter((dataset) => dataset)
      );

      setSelectedDatasetFiltes(
        selectedDatasetFilters.map((datasets, indexFilter) => {
          if (equals(indexFilter, datasetFilterIndex)) {
            return selectedDatasetFilters[indexFilter]
              .map((dataset, i) => {
                if (index < i && !equals(e.target.value, dataset.type)) {
                  return undefined;
                }

                if (equals(i, index)) {
                  return {
                    allOfResourceType: false,
                    ids: [],
                    type: e.target.value as ResourceTypeEnum
                  };
                }

                return dataset;
              })
              .filter((dataset) => dataset) as Array<{
              allOfResourceType: boolean;
              ids: Array<number>;
              type: ResourceTypeEnum;
            }>;
          }

          return datasets;
        })
      );
    };

  const deleteResource = (index: number) => (): void => {
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}`,
      (datasetFilter || []).filter((_, i) => !equals(i, index))
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
    setSelectedDatasetFiltes(
      selectedDatasetFilters.map((datasets, indexFilter) => {
        if (equals(indexFilter, datasetFilterIndex)) {
          return selectedDatasetFilters[indexFilter].filter(
            (_, i) => !equals(i, index)
          );
        }

        return datasets;
      })
    );
  };

  const deleteResourceItem = ({ index, option, resources }): void => {
    const newResource = reject(propEq(option.id, 'id'), resources);

    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${index}.resources`,
      newResource
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
    setSelectedDatasetFiltes(
      selectedDatasetFilters.map((datasets, indexFilter) => {
        if (equals(indexFilter, datasetFilterIndex)) {
          return selectedDatasetFilters[indexFilter].map((dataset, i) => {
            if (equals(i, index)) {
              return {
                allOfResourceType: false,
                ids: dataset.ids.filter((id) => !equals(id, option.id)),
                type: dataset.type
              };
            }

            return dataset;
          });
        }

        return datasets;
      })
    );
  };

  const buildSearchParameters = (
    index: number
  ): Array<QueryParameter> | undefined => {
    const subSlice = selectedDatasetFilters[datasetFilterIndex].slice(0, index);
    if (isEmpty(subSlice) || last(subSlice)?.allOfResourceType) {
      return undefined;
    }

    const searchParameter =
      searchParametersBySelectedResourceType[
        selectedDatasetFilters[datasetFilterIndex][index].type
      ][last(subSlice)?.type];

    return [
      {
        name: 'search',
        value: {
          [searchParameter]: {
            $in: selectedDatasetFilters[datasetFilterIndex][index - 1].ids
          }
        }
      }
    ];
  };

  const getResourceBaseEndpoint =
    (index: number, resourceType: ResourceTypeEnum) =>
    (parameters): string => {
      return buildListingEndpoint({
        baseEndpoint: equals(resourceType, ResourceTypeEnum.BusinessView)
          ? `${baseEndpoint}/bam${resourceTypeBaseEndpoints[ResourceTypeEnum.BusinessView]}`
          : `${baseEndpoint}${resourceTypeBaseEndpoints[resourceType]}`,
        customQueryParameters: buildSearchParameters(index),
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
    deleteButtonHidden,
    deleteResource,
    deleteResourceItem,
    displayAllOfResourceTypeCheckbox,
    error: errorToDisplay,
    getLabelForSelectedResources,
    getResourceBaseEndpoint,
    getResourceTypeOptions,
    getSearchField,
    lowestResourceTypeReached
  };
};

export default useDatasetFilter;
