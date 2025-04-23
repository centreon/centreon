/* eslint-disable no-param-reassign */
import { equals, isEmpty, prop } from 'ramda';

import {
  Dataset,
  DatasetFilter,
  ResourceAccessRule,
  ResourceTypeEnum
} from '../../models';

export const getEmptyInitialValues = (): Omit<ResourceAccessRule, 'id'> => ({
  allContactGroups: false,
  allContacts: false,
  contactGroups: [],
  contacts: [],
  datasetFilters: [
    [
      {
        allOfResourceType: false,
        resourceType: ResourceTypeEnum.Empty,
        resources: []
      }
    ]
  ],
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
        allOfResourceType: isEmpty(element.resources),
        resourceType: element.resourceType,
        resources: element.resources
      }
    ];
  }

  const newDatasets = [
    ...datasets,
    {
      allOfResourceType: isEmpty(element.resources),
      resourceType: element.resourceType,
      resources: element.resources
    }
  ];

  return nestedObjectToArray(prop('datasetFilter', element), newDatasets);
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
  allContactGroups: contactGroups.all,
  allContacts: contacts.all,
  contactGroups: contactGroups.values,
  contacts: contacts.values,
  datasetFilters: formatDatasetFilters(datasetFilters),
  description,
  isActivated,
  name
});
