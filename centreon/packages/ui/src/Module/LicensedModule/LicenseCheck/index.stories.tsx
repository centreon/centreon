import { ComponentMeta, ComponentStory } from '@storybook/react';
import withMock from 'storybook-addon-mock';
import { makeStyles } from 'tss-react/mui';

import { Alert, Container } from '@mui/material';

import { getModuleLicenseCheckEndpoint } from '../api';

import LicenseCheck from '.';

const useStyles = makeStyles()({
  container: {
    height: '100vh'
  }
});

export default {
  argTypes: {
    isLicenseValid: { control: 'boolean' },
    moduleName: { control: 'text' }
  },
  component: LicenseCheck,
  decorators: [withMock],
  title: 'LicenseCheck'
} as ComponentMeta<typeof LicenseCheck>;

const getMockData = ({ moduleName, isLicenseValid }): Array<object> => [
  {
    method: 'GET',
    response: {
      success: isLicenseValid
    },
    status: 200,
    url: getModuleLicenseCheckEndpoint(moduleName)
  }
];

interface Props {
  moduleName: string;
}

const Module = ({ moduleName }: Props): JSX.Element => (
  <Container maxWidth="sm">
    <Alert severity="success">Welcome to {moduleName}</Alert>
  </Container>
);

const Story = ({ moduleName }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <LicenseCheck moduleName={moduleName}>
        <Module moduleName={moduleName} />
      </LicenseCheck>
    </div>
  );
};

const TemplateLicenseCheck: ComponentStory<typeof LicenseCheck> = (args) => (
  <Story {...args} moduleName="paidModule1" />
);
export const PlaygroundLicenseCheck = TemplateLicenseCheck.bind({});
PlaygroundLicenseCheck.args = {
  moduleName: 'paidModule1'
};
PlaygroundLicenseCheck.parameters = {
  mockData: getMockData({ isLicenseValid: true, moduleName: 'paidModule1' })
};

export const withInvalidLicense = (): JSX.Element => (
  <Story moduleName="paidModule2" />
);
withInvalidLicense.parameters = {
  mockData: getMockData({ isLicenseValid: false, moduleName: 'paidModule2' })
};

export const withValidLicense = (): JSX.Element => (
  <Story moduleName="paidModule3" />
);
withValidLicense.parameters = {
  mockData: getMockData({ isLicenseValid: true, moduleName: 'paidModule3' })
};
