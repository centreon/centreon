import { TestQueryProvider } from '@centreon/ui';

import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import Filter from '.';
import useFilter from './useFilter';

const FilterWithLoading = (): JSX.Element => {
  useLoadResources();

  return <Filter />;
};

// The filter with the listing hooks of the Resources page, reading and
// writing the filter saved in the local storage.
const FilterWithListing = (): JSX.Element => {
  useFilter();
  useListing();

  return <FilterWithLoading />;
};

export const Default = (): JSX.Element => (
  <TestQueryProvider>
    <FilterWithListing />
  </TestQueryProvider>
);
