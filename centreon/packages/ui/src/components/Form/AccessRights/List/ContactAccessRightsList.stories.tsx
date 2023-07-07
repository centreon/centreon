import { ReactElement, ReactNode } from 'react';

import { Meta, StoryObj } from '@storybook/react';
import { FormikProvider, useFormik } from 'formik';
import { mixed, object } from 'yup';

import {
  AccessRightsFormProvider,
  AccessRightsFormProviderProps
} from '../useAccessRightsForm';
import { ContactAccessRightResource } from '../AccessRights.resource';
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

  const formik = useFormik<Partial<ContactAccessRightResource>>({
    initialValues: { role: 'viewer' },
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    onSubmit: () => {},
    validationSchema: object({
      role: mixed().oneOf(['viewer', 'editor']).required()
    })
  });

  const initialValues: AccessRightsFormProviderProps['initialValues'] =
    contactAccessRightsMock(amountOfContacts);

  return (
    <AccessRightsFormProvider initialValues={initialValues} options={options}>
      <FormikProvider value={formik}>{children}</FormikProvider>
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
