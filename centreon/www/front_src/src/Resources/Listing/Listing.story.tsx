import { type Column, TestQueryProvider } from '@centreon/ui';
import {
  ListingVariant,
  platformFeaturesAtom,
  userAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import useDetails from '../Details/useDetails';
import useFilter from '../Filter/useFilter';
import { Visualization } from '../models';
import Listing from '.';
import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost
} from './columns';
import { selectedColumnIdsAtom } from './listingAtoms';
import {
  columns,
  columnToSort,
  entities,
  getPlatformFeatures,
  retrievedListing,
  retrievedListingByHosts,
  retrievedListingWithCriticalResources
} from './testUtils';
import useLoadDetails from './useLoadResources/useLoadDetails';

const columnPresets = {
  states: ['resource', 'state', 'information'],
  viewByHost: defaultSelectedColumnIdsforViewByHost
};

const ListingTest = (): JSX.Element => {
  useFilter();
  useLoadDetails();
  useDetails();

  return (
    <div style={{ height: '100vh' }}>
      <Listing />
    </div>
  );
};

interface Props {
  columnPreset?: keyof typeof columnPresets;
  listingVariant?: ListingVariant;
  notification?: boolean;
  visualization?: Visualization;
}

export const Default = ({
  columnPreset,
  listingVariant = ListingVariant.compact,
  notification = false,
  visualization = Visualization.All
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      ...created.get(userAtom),
      locale: 'en_US',
      timezone: 'Europe/Paris',
      user_interface_density: listingVariant
    });
    created.set(selectedVisualizationAtom, visualization);
    created.set(platformFeaturesAtom, getPlatformFeatures({ notification }));
    if (columnPreset) {
      created.set(selectedColumnIdsAtom, columnPresets[columnPreset]);
    }

    return created;
  }, [columnPreset, listingVariant, notification, visualization]);

  return (
    <Provider store={store}>
      <TestQueryProvider>
        <MemoryRouter>
          <ListingTest />
        </MemoryRouter>
      </TestQueryProvider>
    </Provider>
  );
};

const columnData = ({ id, label, shortLabel, sortable }: Column): object => ({
  id,
  label,
  shortLabel,
  sortable
});

// Test data the spec cannot import: its module loads React components.
export const Data = (): null => {
  Object.assign(window, {
    resourceListingData: {
      columns: columns.map(columnData),
      columnToSort: columnToSort.map(columnData),
      defaultSelectedColumnIds,
      entities,
      retrievedListing,
      retrievedListingByHosts,
      retrievedListingWithCriticalResources
    }
  });

  return null;
};
