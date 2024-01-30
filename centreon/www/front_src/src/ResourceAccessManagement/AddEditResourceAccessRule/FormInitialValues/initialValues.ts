import { equals, prop } from 'ramda';

import { DatasetFilter, NamedEntity, ResourceTypeEnum } from '../../models';

export const getEmptyInitialValues = (): object => ({
  contactGroups: [],
  contacts: [],
  datasetFilters: [[{ resourceType: ResourceTypeEnum.Empty, resources: [] }]],
  description: '',
  isActivated: true,
  name: ''
});

type __Dataset = {
  resourceType: ResourceTypeEnum;
  resources: Array<NamedEntity>;
};

const formatDatasetFilter = (
  datasetFilter: DatasetFilter
): Array<__Dataset> => {
  let datasets: Array<__Dataset> = [];
  while (!equals(prop('datasetFilter', datasetFilter), null)) {
    datasets = [
      ...datasets,
      {
        resourceType: datasetFilter.resourceType,
        resources: datasetFilter.resources
      }
    ];
    datasetFilter = prop('datasetFilter', datasetFilter);
  }

  datasets = [
    ...datasets,
    {
      resourceType: datasetFilter.resourceType,
      resources: datasetFilter.resources
    }
  ];

  return datasets;
};

const formatDatasetFilters = (
  datasetFilters: Array<DatasetFilter>
): Array<Array<__Dataset>> =>
  datasetFilters.map((datasetFilter) => formatDatasetFilter(datasetFilter));

export const getInitialValues = ({
  contactGroups,
  contacts,
  datasetFilters,
  description,
  isActivated,
  name
}): object => ({
  contactGroups,
  contacts,
  datasetFilters: formatDatasetFilters(datasetFilters),
  description,
  isActivated,
  name
});
