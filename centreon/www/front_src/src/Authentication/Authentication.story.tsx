import { TestQueryProvider } from '@centreon/ui';

import Authentication from '.';
import OpenidConfigurationForm from './Openid';
import SAMLConfigurationForm from './SAML';
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

export const SAML = (): JSX.Element => (
  <TestQueryProvider>
    <SAMLConfigurationForm />
  </TestQueryProvider>
);

export const OpenId = (): JSX.Element => (
  <TestQueryProvider>
    <OpenidConfigurationForm />
  </TestQueryProvider>
);
