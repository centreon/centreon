import { ComponentMeta, ComponentStory } from '@storybook/react';

import MultiCheckbox from '.';

const initialValues = ['ok', 'warning', 'critical', 'unknown'];

const values = ['ok', 'critical'];

export default {
  argTypes: {},
  component: MultiCheckbox,
  title: 'Checkbox/Multi'
} as ComponentMeta<typeof MultiCheckbox>;

const Template: ComponentStory<typeof MultiCheckbox> = (args) => (
  <MultiCheckbox {...args} />
);

export const Playground = Template.bind({});

Playground.args = {
  initialValues,
  values
};

export const Horisontal = Template.bind({});

Horisontal.args = {
  initialValues,
  row: true,
  values
};

export const LabelOnTop = Template.bind({});

LabelOnTop.args = {
  initialValues,
  labelPlacement: 'top',
  row: true,
  values
};
