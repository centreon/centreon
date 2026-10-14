import { TestQueryProvider } from '@centreon/ui';

import Authentication from '.';
import WebSSOConfigurationForm from './WebSSO';

export const Default = (): JSX.Element => (
  <TestQueryProvider>
    <Authentication />
  </TestQueryProvider>
);

export const WebSSO = (): JSX.Element => (
  <TestQueryProvider>
    <WebSSOConfigurationForm />
  </TestQueryProvider>
);
