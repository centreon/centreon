import { ComponentMeta, ComponentStory } from '@storybook/react';

import MailIcon from '@mui/icons-material/MailOutline';
import SmsIcon from '@mui/icons-material/TextsmsOutlined';
import FacebookIcon from '@mui/icons-material/Facebook';

import MultiCheckbox from '.';

const values = [
  {
    checked: false,
    label: 'Mail'
  },
  {
    checked: true,
    label: 'Sms'
  },
  {
    checked: false,
    label: 'Facebook'
  }
];

const valuesWithIcons = [
  {
    Icon: MailIcon,
    checked: false,
    label: 'Mail'
  },
  {
    Icon: SmsIcon,
    checked: true,
    label: 'Sms'
  },
  {
    Icon: FacebookIcon,
    checked: false,
    label: 'Facebook'
  }
];

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
  values
};

export const Horisontal = Template.bind({});

Horisontal.args = {
  row: true,
  values
};

export const WithIcons = Template.bind({});

WithIcons.args = {
  row: true,
  values: valuesWithIcons
};

export const LabelOnTop = Template.bind({});

LabelOnTop.args = {
  labelPlacement: 'top',
  row: true,
  values
};
