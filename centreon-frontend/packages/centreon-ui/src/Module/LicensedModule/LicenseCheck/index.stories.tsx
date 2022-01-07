import * as React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import { Alert, Container } from '@mui/material';

import { getModuleLicenseCheckEndpoint } from '../api';

import LicenseCheck from '.';

export default { title: 'LicenseCheck' };

const mockedAxios = new MockAdapter(axios);

const moduleName = 'paidModule';

const endpoint = getModuleLicenseCheckEndpoint(moduleName);

const Module = (): JSX.Element => (
  <Container maxWidth="sm">
    <Alert severity="success">Welcome to {moduleName}</Alert>
  </Container>
);

interface Props {
  isLicenseValid: boolean;
}
const Story = ({ isLicenseValid }: Props): JSX.Element => {
  mockedAxios.onGet(endpoint).reply(() => [200, { success: isLicenseValid }]);

  return (
    <LicenseCheck moduleName={moduleName}>
      <Module />
    </LicenseCheck>
  );
};

export const withInvalidLicense = (): JSX.Element => (
  <Story isLicenseValid={false} />
);

export const withValidLicense = (): JSX.Element => <Story isLicenseValid />;
