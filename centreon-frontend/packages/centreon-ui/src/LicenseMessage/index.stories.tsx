import { ComponentMeta, ComponentStory } from '@storybook/react';

import LicenseMessage from '.';

export default {
  argTypes: {
    label: { control: 'text' },
  },
  component: LicenseMessage,
  title: 'License Message',
} as ComponentMeta<typeof LicenseMessage>;

const TemplateLicenseMessage: ComponentStory<typeof LicenseMessage> = (
  args,
) => <LicenseMessage {...args} />;

export const PlaygroundLicenseMessage = TemplateLicenseMessage.bind({});

export const normal = (): JSX.Element => <LicenseMessage />;

export const withLabel = (): JSX.Element => (
  <LicenseMessage label="This is a license message" />
);
