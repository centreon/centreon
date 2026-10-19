import { TestQueryProvider } from '@centreon/ui';

import useLoadingNotifications from '../Listing/useLoadNotifications';
import Filter from '.';

// The listing hook is mounted next to the filter: it sends the requests.
const FilterWithListing = (): JSX.Element => {
  useLoadingNotifications();

  return <Filter />;
};

export const Default = (): JSX.Element => (
  <TestQueryProvider>
    <FilterWithListing />
  </TestQueryProvider>
);
