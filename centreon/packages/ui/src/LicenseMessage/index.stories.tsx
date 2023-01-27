import { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import LicenseMessage from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    background: theme.palette.error.main,
    color: theme.palette.common.white,
    padding: theme.spacing(1)
  }
}));
export default {
  argTypes: {
    label: { control: 'text' }
  },
  component: LicenseMessage,
  title: 'License Message'
} as ComponentMeta<typeof LicenseMessage>;

const TemplateLicenseMessage: ComponentStory<typeof LicenseMessage> = (
  args
) => <LicenseMessage {...args} />;

export const PlaygroundLicenseMessage = TemplateLicenseMessage.bind({});

export const normal = (): JSX.Element => <LicenseMessage />;

export const withLabel = (): JSX.Element => (
  <LicenseMessage label="This is a license message" />
);

const CustomLicenseMessage = (): JSX.Element => {
  const { classes } = useStyles();

  return <LicenseMessage className={classes.root} />;
};

export const customLicenseMessage = (): JSX.Element => <CustomLicenseMessage />;
