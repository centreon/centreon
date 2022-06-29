import { ComponentMeta, ComponentStory } from '@storybook/react';
import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import { Alert, Container } from '@mui/material';

import { getModuleLicenseCheckEndpoint } from '../api';

import LicenseCheck from '.';

export default {
  argTypes: {
    isLicenseValid: { control: 'boolean' },
    moduleName: { control: 'text' },
  },
  component: LicenseCheck,
  title: 'LicenseCheck',
} as ComponentMeta<typeof LicenseCheck>;

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

const TemplateLicenseCheck: ComponentStory<typeof LicenseCheck> = (args) => (
  <Story {...args} isLicenseValid={false} />
);
export const PlaygroundLicenseCheck = TemplateLicenseCheck.bind({});
PlaygroundLicenseCheck.args = {
  moduleName: 'Paid Module',
};

export const withInvalidLicense = (): JSX.Element => (
  <Story isLicenseValid={false} />
);

export const withValidLicense = (): JSX.Element => <Story isLicenseValid />;
