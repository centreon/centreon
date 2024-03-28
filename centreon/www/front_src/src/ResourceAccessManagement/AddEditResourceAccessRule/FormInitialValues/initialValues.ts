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

const nestedObjectToArray = (
  element: DatasetFilter,
  datasets: Array<Dataset>
): Array<Dataset> => {
  if (equals(prop('datasetFilter', element), null)) {
    return [
      ...datasets,
      {
        resourceType: element.resourceType,
        resources: element.resources
      }
    ];
  }

  datasets = [
    ...datasets,
    {
      resourceType: element.resourceType,
      resources: element.resources
    }
  ];

  return nestedObjectToArray(prop('datasetFilter', element), datasets);
};

const formatDatasetFilter = (datasetFilter: DatasetFilter): Array<Dataset> => {
  const datasets: Array<Dataset> = [];

  return nestedObjectToArray(datasetFilter, datasets);
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
