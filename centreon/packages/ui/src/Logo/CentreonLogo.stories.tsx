import { ComponentMeta, ComponentStory } from '@storybook/react';

import { CentreonLogo } from './CentreonLogo';

export default {
  argTypes: {
    className: { control: false },
    fill: { control: 'text' }
  },
  component: CentreonLogo,
  title: 'Logo/CentreonLogo'
} as ComponentMeta<typeof CentreonLogo>;

const Logo: ComponentStory<typeof CentreonLogo> = (args) => (
  <CentreonLogo {...args} />
);

export const Default = Logo.bind({});
Logo.args = {};
