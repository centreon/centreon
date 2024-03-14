import { isNil, map, path, pluck, prop } from 'ramda';

import { DatasetFilter } from '../../models';

interface ODatasetFilter {
  dataset_filter?: ODatasetFilter | null;
  resources: Array<number>;
  type: string;
}

const adaptDatasetFilter = (datasetFilter: DatasetFilter): ODatasetFilter => {
  if (isNil(path(['datasetFilter'], datasetFilter))) {
    return {
      dataset_filter: null,
      resources: pluck('id', datasetFilter.resources),
      type: datasetFilter.resourceType
    };
  }

  return {
    dataset_filter: adaptDatasetFilter(path(['datasetFilter'], datasetFilter)),
    resources: pluck('id', datasetFilter.resources),
    type: datasetFilter.resourceType
  };
};

export const adaptRule = ({
  contactGroups,
  contacts,
  datasetFilters,
  description,
  isActivated,
  name
}): object => ({
  contact_groups: map(prop('id'), contactGroups),
  contacts: map(prop('id'), contacts),
  dataset_filters: datasetFilters.map((datasetFilter: DatasetFilter) =>
    adaptDatasetFilter(datasetFilter)
  ),
  description,
  is_enabled: isActivated,
  name
});
