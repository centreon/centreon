import { ComponentMeta, ComponentStory } from '@storybook/react';

import CheckboxGroup from '.';

const options = ['ok', 'warning', 'critical', 'unknown'];

const values = ['ok', 'critical'];

export default {
  argTypes: {},
  component: CheckboxGroup,
  title: 'Checkbox/Multi'
} as ComponentMeta<typeof CheckboxGroup>;

const Template: ComponentStory<typeof CheckboxGroup> = (args) => (
  <CheckboxGroup {...args} />
);

export const Playground = Template.bind({});

Playground.args = {
  options,
  values
};

export const Horizontal = Template.bind({});

Horizontal.args = {
  direction: 'horizontal',
  options,
  values
};

export const LabelOnTop = Template.bind({});

LabelOnTop.args = {
  direction: 'horizontal',
  labelPlacement: 'top',
  options,
  values
};
