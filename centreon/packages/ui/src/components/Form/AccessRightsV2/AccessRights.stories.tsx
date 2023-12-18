import { faker } from '@faker-js/faker';
import { Meta, StoryObj } from '@storybook/react';
import { rest } from 'msw'

import { SelectEntry, SnackbarProvider } from '../../..';
import { Listing } from '../../../api/models';

import { AccessRightInitialValues, Labels } from './models';
import AccessRights from './AccessRights';

faker.seed(42);

const accessRights: Array<AccessRightInitialValues> = Array(10)
  .fill(0)
  .map((_, idx) => ({
    email: faker.internet.email(),
    id: idx,
    isContactGroup: idx % 5 === 0,
    name: faker.person.fullName(),
    role: idx % 2 === 0 ? 'viewer' : 'editor'
  }));

const labels: Labels = {
  actions: {
    copyError: 'Failed to copy',
    copySuccess: 'Copied',
    cancel: 'Cancel',
    copyLink: 'Copy link',
    save: 'Save'
  },
  add: {
    autocompleteContact: 'Add a contact',
    autocompleteContactGroup: 'Add a contact group',
    contact: 'Contact',
    contactGroup: 'Contact group',
    title: 'Share dashboard with'
  },
  list: {
    added: 'Added',
    group: 'Group',
    removed: 'Removed',
    title: 'User rights',
    updated: 'Updated'
  }
};

const roles = [
  {
    id: 'viewer',
    name: 'Viewer'
  },
  {
    id: 'editor',
    name: 'Editor'
  }
];

const buildEntities = (from, isGroup): Array<SelectEntry> => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      id: 1000 + index,
      name: `Entity ${isGroup ? 'Group' : ''} ${from + index}`,
      email: isGroup ? undefined : faker.internet.email()
    }));
};

const buildResult = (isGroup): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page: 1,
    total: 10
  },
  result: buildEntities(10, isGroup)
});

const meta: Meta<typeof AccessRights> = {
  component: AccessRights,
  parameters: {
    msw: {
      handlers: [
        rest.get('api/latest/contact?**', (req, res, ctx) => {
          return res(
            ctx.json(buildResult(false))
          )
        }),
        rest.get('api/latest/contactGroup?**', (req, res, ctx) => {
          return res(
            ctx.json(buildResult(true))
          )
        }),
      ]
    },
  }
};

export default meta;
type Story = StoryObj<typeof AccessRights>;

export const Default: Story = {
  args: {
    cancel: () => undefined,
    endpoints: {
      contact: '/contact',
      contactGroup: '/contactGroup'
    },
    initialValues: accessRights,
    labels,
    roles,
    link: 'link',
    submit: console.log
  },
  render: (args) => (
    <SnackbarProvider>
      <AccessRights {...args} />
    </SnackbarProvider>
  )
};
