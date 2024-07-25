import { Meta, StoryObj } from '@storybook/react';
import { http, HttpResponse } from 'msw';

import { SnackbarProvider } from '../../..';

import AccessRightsForm from './AccessRights';
import {
  accessRightsWithStates,
  buildResult,
  defaultAccessRights,
  emptyAccessRights,
  labels,
  roles
} from './storiesData';

const meta: Meta<typeof AccessRightsForm> = {
  component: AccessRightsForm,
  parameters: {
    msw: {
      handlers: [
        http.get('api/latest/contact?**', () => {
          return HttpResponse.json(buildResult(false));
        }),
        http.get('api/latest/contactGroup?**', () => {
          return HttpResponse.json(buildResult(true));
        })
      ]
    }
  }
};

const Template = (args): JSX.Element => (
  <SnackbarProvider>
    <AccessRightsForm {...args} />
  </SnackbarProvider>
);

export default meta;
type Story = StoryObj<typeof AccessRightsForm>;

export const Default: Story = {
  args: {
    cancel: () => undefined,
    endpoints: {
      contact: '/contact',
      contactGroup: '/contactGroup'
    },
    initialValues: defaultAccessRights,
    labels,
    roles,
    submit: () => undefined
  },
  render: Template
};

export const AccessRightsWithStates: Story = {
  args: {
    cancel: () => undefined,
    endpoints: {
      contact: '/contact',
      contactGroup: '/contactGroup'
    },
    initialValues: accessRightsWithStates,
    labels,
    roles,
    submit: () => undefined
  },
  render: Template
};

export const withEmptyState: Story = {
  args: {
    cancel: () => undefined,
    endpoints: {
      contact: '/contact',
      contactGroup: '/contactGroup'
    },
    initialValues: emptyAccessRights,
    labels,
    roles,
    submit: () => undefined
  },
  render: Template
};

export const loading: Story = {
  args: {
    cancel: () => undefined,
    endpoints: {
      contact: '/contact',
      contactGroup: '/contactGroup'
    },
    initialValues: emptyAccessRights,
    labels,
    loading: true,
    roles,
    submit: () => undefined
  },
  render: Template
};
