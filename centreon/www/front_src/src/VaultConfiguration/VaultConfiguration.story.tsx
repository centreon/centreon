import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import VaultConfiguration from './VaultConfiguration';

export const Default = (): JSX.Element => (
  <TestQueryProvider>
    <SnackbarProvider>
      <VaultConfiguration />
    </SnackbarProvider>
  </TestQueryProvider>
);
