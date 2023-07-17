import { ReactElement, ReactNode } from 'react';

import { Meta, StoryObj } from '@storybook/react';

import {
  AccessRightsFormProvider,
  AccessRightsFormProviderProps
} from '../useAccessRightsForm';
import { contactAccessRightsMock } from '../__fixtures__/contactAccessRight.mock';

import { ContactAccessRightsList } from './ContactAccessRightsList';

const meta: Meta<typeof ContactAccessRightsList> = {
  component: ContactAccessRightsList,
  title: 'components/Form/AccessRights/ContactAccessRightsList'
};

export default meta;
type Story = StoryObj<typeof ContactAccessRightsList>;

const Wrapper = ({
  children,
  amountOfContacts = 12
}: {
  amountOfContacts?: number;
  children: ReactNode;
}): ReactElement => {
  const options: AccessRightsFormProviderProps['options'] = {
    contacts: [],
    roles: [{ role: 'viewer' }, { role: 'editor' }]
  };

  const initialValues: AccessRightsFormProviderProps['initialValues'] =
    contactAccessRightsMock(amountOfContacts);

  return (
    <AccessRightsFormProvider initialValues={initialValues} options={options}>
      {children}
    </AccessRightsFormProvider>
  );
};

export const Default: Story = {
  args: {
    labels: {
      emptyState: 'No Contacts with access rights',
      item: {
        group: 'Group',
        state: {
          added: 'Add',
          removed: 'Remove',
          updated: 'Update'
        }
      }
    }
  },
  render: (args) => (
    <Wrapper>
      <ContactAccessRightsList {...args} />
    </Wrapper>
  )
};

export const AsEmptyState: Story = {
  args: Default.args,
  render: (args) => (
    <Wrapper amountOfContacts={0}>
      <ContactAccessRightsList {...args} />
    </Wrapper>
  )
};

const LoadingWrapper = ({
  children
}: {
  children: ReactNode;
}): ReactElement => {
  const options: AccessRightsFormProviderProps['options'] = {
    contacts: [],
    roles: [{ role: 'viewer' }, { role: 'editor' }]
  };

  return (
    <AccessRightsFormProvider loadingStatus="loading" options={options}>
      {children}
    </AccessRightsFormProvider>
  );
};

export const AsLoadingState: Story = {
  args: Default.args,
  render: (args) => (
    <LoadingWrapper>
      <ContactAccessRightsList {...args} />
    </LoadingWrapper>
  )
};
