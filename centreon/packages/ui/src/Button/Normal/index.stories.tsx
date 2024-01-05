import { Meta, StoryObj } from '@storybook/react';

import Button from './index';

const meta: Meta<typeof Button> = {
  component: Button
};

export default meta;
type Story = StoryObj<typeof Button>;

const Template: Story = {
  render: (args) => <Button {...args} />
};

const buttonProps = {
  onClick: () => {
    alert('hi');
  }
};

export const normal: Story = {
  ...Template,
  args: {
    buttonProps,
    label: 'normal button'
  }
};

export const customize: Story = {
  ...Template,
  args: {
    buttonProps: {
      ...buttonProps,
      variant: 'text'
    },
    label: 'customized button',
    labelProps: { variant: 'body2' }
  }
};
