import { Meta, StoryObj } from '@storybook/react';

import { Gauge } from './Gauge';

const meta: Meta<typeof Gauge> = {
  component: Gauge
};

export default meta;
type Story = StoryObj<typeof Gauge>;

export const Default: Story = {
  args: {}
};
