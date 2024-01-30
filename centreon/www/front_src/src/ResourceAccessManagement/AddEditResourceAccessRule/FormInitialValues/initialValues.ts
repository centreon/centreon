/* eslint-disable no-param-reassign */
import { equals, prop } from 'ramda';

import {
  Dataset,
  DatasetFilter,
  ResourceAccessRule,
  ResourceTypeEnum
} from '../../models';

export const getEmptyInitialValues = (): Omit<ResourceAccessRule, 'id'> => ({
  contactGroups: [],
  contacts: [],
  datasetFilters: [[{ resourceType: ResourceTypeEnum.Empty, resources: [] }]],
  description: '',
  isActivated: true,
  name: ''
});

const formatDatasetFilter = (datasetFilter: DatasetFilter): Array<Dataset> => {
  let datasets: Array<Dataset> = [];
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
): Array<Array<Dataset>> =>
  datasetFilters.map((datasetFilter) => formatDatasetFilter(datasetFilter));

export const getInitialValues = ({
  contactGroups,
  contacts,
  datasetFilters,
  description,
  isActivated,
  name
}): Omit<ResourceAccessRule, 'id'> => ({
  contactGroups,
  contacts,
  datasetFilters: formatDatasetFilters(datasetFilters),
  description,
  isActivated,
  name
});
