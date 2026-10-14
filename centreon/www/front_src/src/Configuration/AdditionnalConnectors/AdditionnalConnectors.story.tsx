import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { MemoryRouter } from 'react-router';

import AdditionnalConnectors from '.';

export const Default = (): JSX.Element => (
  <MemoryRouter>
    <SnackbarProvider>
      <TestQueryProvider>
        <div style={{ height: '100vh' }}>
          <AdditionnalConnectors />
        </div>
      </TestQueryProvider>
    </SnackbarProvider>
  </MemoryRouter>
);
