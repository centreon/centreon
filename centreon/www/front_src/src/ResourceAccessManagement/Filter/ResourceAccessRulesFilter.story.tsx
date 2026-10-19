import { TestQueryProvider } from '@centreon/ui';

import useListing from '../Listing/useListing';
import Filter from '.';

// The listing hook is mounted next to the filter: it sends the requests.
const FilterWithListing = (): JSX.Element => {
  useListing();

  return <Filter />;
};

export const Default = (): JSX.Element => (
  <TestQueryProvider>
    <FilterWithListing />
  </TestQueryProvider>
);
