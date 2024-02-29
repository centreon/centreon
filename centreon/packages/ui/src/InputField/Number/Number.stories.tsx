/* eslint-disable no-console */
import { Meta, StoryObj } from '@storybook/react';

import NumberField from './Number';

const meta: Meta<typeof NumberField> = {
  argTypes: {
    defaultValue: {
      defaultValue: 0,
      description:
        'The initial value which will be used by the input for the first render',
      type: 'number'
    },
    fallbackValue: {
      defaultValue: 0,
      description: 'This value will be used when the input is cleared',
      type: 'number'
    },
    onChange: {
      description:
        'The change function with the actual value as parameter. This parameter will be the value when the input is filled but it will be the fallbackValue when the input is cleared out',
      type: 'function'
    }
  },
  component: NumberField
};

export default meta;
type Story = StoryObj<typeof NumberField>;

export const Default: Story = {
  args: {
    onChange: console.log
  }
};

export const WithDefaultValue: Story = {
  args: {
    defaultValue: 25,
    onChange: console.log
  }
};

export const WithFallbackValue: Story = {
  args: {
    fallbackValue: 25,
    onChange: console.log
  }
};

export const WithFallbackValueAndDefaultValue: Story = {
  args: {
    defaultValue: 10,
    fallbackValue: 25,
    onChange: console.log
  }
};

export const WithFallbackValueAndDefaultValueAutoSize: Story = {
  args: {
    autoSize: true,
    defaultValue: 10,
    fallbackValue: 25,
    onChange: console.log
  }
};
