import { isNil, map, pluck, prop } from 'ramda';

type Resource = {
  [key: string]: number | string | boolean | null | undefined;
  id: number;
};

type Dataset = {
  resourceType: string;
  resources: Array<Resource>;
};

type ODataset = {
  resources: Array<number>;
  type: string;
};

type ODatasetFilter = {
  dataset_filter?: ODatasetFilter | null;
  resources: Array<number>;
  type: string;
};

const adaptResources = (resources: Array<Resource>): Array<number> =>
  pluck('id', resources);

const adaptDataset = ({ resourceType, resources }: Dataset): ODataset => ({
  resources: adaptResources(resources),
  type: resourceType
});

const adaptDatasetFilter = (datasetFilter: Array<Dataset>): Array<ODataset> =>
  datasetFilter.map((dataset) => adaptDataset(dataset));

const arrayToNestedObject = (items: Array<ODataset>): ODatasetFilter => {
  const currentItem = items[0];
  const nextItem = items[1];
  if (isNil(nextItem)) {
    return {
      dataset_filter: null,
      resources: currentItem.resources,
      type: currentItem.type
    };
  }

  return {
    dataset_filter: arrayToNestedObject(items.slice(1)),
    resources: currentItem.resources,
    type: currentItem.type
  };
};

const adaptDatasetFilters = (
  datasetFilters: Array<Array<Dataset>>
): Array<ODatasetFilter> =>
  datasetFilters.map((datasetFilter) =>
    arrayToNestedObject(adaptDatasetFilter(datasetFilter))
  );

export const adaptResourceAccessRule = ({
  allContactGroups,
  allContacts,
  contactGroups,
  contacts,
  datasetFilters,
  description,
  isActivated,
  name
}): object => ({
  contact_groups: {
    all: allContactGroups,
    ids: map(prop('id'), contactGroups)
  },
  contacts: {
    all: allContacts,
    ids: map(prop('id'), contacts)
  },
  dataset_filters: adaptDatasetFilters(datasetFilters),
  description,
  is_enabled: isActivated,
  name
});
