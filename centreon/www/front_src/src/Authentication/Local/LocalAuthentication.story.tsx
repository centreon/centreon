import { TestQueryProvider } from '@centreon/ui';

import { MemoryRouter } from 'react-router';

import LocalAuthentication from '.';

export const Default = (): JSX.Element => (
  <MemoryRouter>
    <TestQueryProvider>
      <LocalAuthentication />
    </TestQueryProvider>
  </MemoryRouter>
);
