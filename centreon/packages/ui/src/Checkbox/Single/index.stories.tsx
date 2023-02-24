import { ComponentMeta, ComponentStory } from '@storybook/react';

import MailIcon from '@mui/icons-material/MailOutline';

import SingleCheckbox from '.';

export default {
  argTypes: {},
  component: SingleCheckbox,
  title: 'Checkbox/Single'
} as ComponentMeta<typeof SingleCheckbox>;

const Template: ComponentStory<typeof SingleCheckbox> = (args) => (
  <SingleCheckbox {...args} />
);

export const Playground = Template.bind({});

Playground.args = {
  checked: true,
  label: 'Up'
};

export const WithIcon = Template.bind({});

WithIcon.args = {
  Icon: MailIcon,
  checked: true,
  label: 'Mail',
  labelPlacement: 'end'
};

export const LabelOnTop = Template.bind({});

LabelOnTop.args = {
  checked: true,
  label: 'Down',
  labelPlacement: 'top'
};
