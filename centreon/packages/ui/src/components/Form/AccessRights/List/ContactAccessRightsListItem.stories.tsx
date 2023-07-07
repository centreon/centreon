import { ReactElement, ReactNode } from 'react';

import { Meta, StoryObj } from '@storybook/react';

import {
  AccessRightsFormProvider,
  AccessRightsFormProviderProps
} from '../useAccessRightsForm';
import { ContactAccessRightResource } from '../AccessRights.resource';

import { ContactAccessRightsListItem } from './ContactAccessRightsListItem';

const meta: Meta<typeof ContactAccessRightsListItem> = {
  component: ContactAccessRightsListItem,
  title: 'components/Form/AccessRights/ContactAccessRightsListItem'
};

export default meta;
type Story = StoryObj<typeof ContactAccessRightsListItem>;

const Wrapper = ({ children }: { children: ReactNode }): ReactElement => {
  const options: AccessRightsFormProviderProps['options'] = {
    contacts: [],
    roles: [{ role: 'viewer' }, { role: 'editor' }]
  };

  return (
    <AccessRightsFormProvider options={options}>
      {children}
    </AccessRightsFormProvider>
  );
};

export const Default: Story = {
  args: {
    labels: {
      group: 'Group',
      state: {
        added: 'Add',
        removed: 'Remove',
        updated: 'Update'
      }
    },
    resource: {
      contactAccessRight: {
        contact: {
          email: 'user@company.com',
          id: '1',
          name: 'User Name'
        },
        role: 'viewer'
      },
      state: 'unchanged',
      stateHistory: []
    }
  },
  render: (args) => (
    <Wrapper>
      <ContactAccessRightsListItem {...args} />
    </Wrapper>
  )
};

export const AsContactGroup: Story = {
  args: {
    labels: Default.args?.labels,
    resource: {
      contactAccessRight: {
        contact: {
          id: '1',
          name: 'Team Name'
        },
        role: 'editor'
      },
      state: 'unchanged',
      stateHistory: []
    }
  },
  render: Default.render
};

export const WithAddedState: Story = {
  args: {
    labels: Default.args?.labels,
    resource: {
      contactAccessRight: {
        ...Default.args?.resource?.contactAccessRight
      } as ContactAccessRightResource,
      state: 'added',
      stateHistory: []
    }
  },
  render: Default.render
};

export const WithRemovedState: Story = {
  args: {
    labels: Default.args?.labels,
    resource: {
      contactAccessRight: {
        ...Default.args?.resource?.contactAccessRight
      } as ContactAccessRightResource,
      state: 'removed',
      stateHistory: []
    }
  },
  render: Default.render
};
