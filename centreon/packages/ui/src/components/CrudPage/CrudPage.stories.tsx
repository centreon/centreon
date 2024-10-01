import { Meta, StoryObj } from '@storybook/react';
import { CrudPage } from '.';

const meta: Meta<typeof CrudPage> = {
  component: CrudPage
};

export default meta;
type Story = StoryObj<typeof CrudPage>;

export const Default: Story = {};
