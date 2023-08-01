import { ReactElement, ReactNode } from 'react';

import { Meta, StoryObj } from '@storybook/react';

import {
  AccessRightsFormProvider,
  AccessRightsFormProviderProps
} from '../useAccessRightsForm';
import { contactsAndGroupsMock } from '../__fixtures__/contactAccessRight.mock';

import { ContactAccessRightInput } from './ContactAccessRightInput';

const meta: Meta<typeof ContactAccessRightInput> = {
  component: ContactAccessRightInput,
  title: 'components/Form/AccessRights/ContactAccessRightInput'
};

export default meta;
type Story = StoryObj<typeof ContactAccessRightInput>;

const Wrapper = ({ children }: { children: ReactNode }): ReactElement => {
  const options: AccessRightsFormProviderProps['options'] = {
    contacts: contactsAndGroupsMock(25),
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
      actions: {
        add: 'Add'
      },
      fields: {
        contact: {
          group: 'group',
          noOptionsText: 'No contacts found',
          placeholder: 'Add Contact...'
        }
      }
    }
  },
  render: (args) => (
    <Wrapper>
      <ContactAccessRightInput labels={args.labels} />
    </Wrapper>
  )
};
