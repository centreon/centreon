import { Meta, StoryObj } from '@storybook/react';
import { DashboardForm } from './DashboardForm';

const meta: Meta<typeof DashboardForm> = {
  component: DashboardForm
};

export default meta;
type Story = StoryObj<typeof DashboardForm>

export const Default: Story = {
  args: {
    labels: {
      title: {
        create: 'Create dashboard',
        update: 'Update dashboard'
      },
      entity: {
        name: 'Name',
        description: 'Description'
      },
      actions: {
        submit: {
          create: 'Create',
          update: 'Update'
        },
        cancel: 'Cancel'
      }
    }
  }
};

export const AsUpdateVariant: Story = {
  args: {
    ...Default.args,
    variant: 'update',
    resource: {
      id: '1',
      name: 'Dashboard 1',
      description: 'Description 1'
    }
  }
};