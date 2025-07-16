import { Meta, StoryObj } from '@storybook/react';

import { DataTableItem } from './DataTableItem';
import '../../../ThemeProvider/tailwindcss.css';

const meta: Meta<typeof DataTableItem> = {
  component: DataTableItem
};

export default meta;
type Story = StoryObj<typeof DataTableItem>;

export const Default: Story = {
  args: {
    description: 'DataTable item description',
    title: 'DataTable item'
  }
};

export const WithActions: Story = {
  args: {
    ...Default.args,
    hasActions: true,
    hasCardAction: true
  }
};
