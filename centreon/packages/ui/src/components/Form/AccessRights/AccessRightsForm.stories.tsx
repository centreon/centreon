import { Meta, StoryObj } from '@storybook/react';

import { AccessRightsForm } from './AccessRightsForm';
import { Default as DefaultContactAccessRightsListStory } from './List/ContactAccessRightsList.stories';
import { Default as DefaultContactAccessRightInputStory } from './Input/ContactAccessRightInput.stories';
import { ContactAccessRightsListProps } from './List/ContactAccessRightsList';
import { ContactAccessRightInputProps } from './Input/ContactAccessRightInput';
import {
  contactAccessRightsMock,
  contactsAndGroupsMock
} from './__fixtures__/contactAccessRight.mock';

const meta: Meta<typeof AccessRightsForm> = {
  component: AccessRightsForm
};

export default meta;
type Story = StoryObj<typeof AccessRightsForm>;

export const Default: Story = {
  args: {
    initialValues: contactAccessRightsMock(12),
    labels: {
      actions: {
        cancel: 'Cancel',
        copyLink: 'Copy link',
        submit: 'Update'
      },
      input: {
        ...DefaultContactAccessRightInputStory.args?.labels
      } as ContactAccessRightInputProps['labels'],
      list: {
        ...DefaultContactAccessRightsListStory.args?.labels
      } as ContactAccessRightsListProps['labels'],
      stats: {
        added: 'added',
        removed: 'removed',
        updated: 'updated'
      }
    },
    options: {
      contacts: contactsAndGroupsMock(25),
      roles: [{ role: 'viewer' }, { role: 'editor' }]
    }
  }
};

export const AsEmptyState: Story = {
  args: {
    labels: Default.args?.labels,
    options: Default.args?.options
  }
};
