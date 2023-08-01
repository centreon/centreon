import { ReactElement } from 'react';

import { FormikProvider, useFormik } from 'formik';
import { mixed, object } from 'yup';

import { useAccessRightsForm } from '../useAccessRightsForm';
import { ContactAccessRightResource } from '../AccessRights.resource';

import { useStyles } from './ContactAccessRightsInput.styles';
import { ContactInputField, ContactInputFieldProps } from './ContactInputField';
import { AddAction } from './AddAction';
import { RoleInputField } from './RoleInputField';

export type ContactAccessRightInputProps = {
  labels: ContactAccessRightInputLabels;
  onAddContactAccessRight?: (value: ContactAccessRightResource) => void;
};

type ContactAccessRightInputLabels = {
  actions?: {
    add: string;
  };
  fields: {
    contact: ContactInputFieldProps['labels'];
    role?: {
      label: string;
    };
  };
};

const ContactAccessRightInput = ({
  labels,
  onAddContactAccessRight
}: ContactAccessRightInputProps): ReactElement => {
  const { classes } = useStyles();
  const { addContactAccessRight, options } = useAccessRightsForm();

  const formik = useFormik<ContactAccessRightResource>({
    initialValues: { contact: null, role: 'viewer' },
    onSubmit: (values, { resetForm }) => {
      addContactAccessRight(values);
      onAddContactAccessRight?.(values);
      resetForm();
    },
    validationSchema: object({
      contact: mixed()
        .test(
          'is-available-contact',
          (d) => `${d.path} is not a contact available from the list`,
          (value) => !!options.contacts?.find((c) => c.id === value?.id)
        )
        .required(),
      role: mixed().oneOf(['viewer', 'editor']).required()
    })
  });

  return (
    <div className={classes.contactAccessRightsInput}>
      <FormikProvider value={formik}>
        <ContactInputField
          id="contact"
          labels={labels.fields.contact}
          name="contact"
        />
        <RoleInputField id="role" name="role" {...labels?.fields?.role} />
        <AddAction />
      </FormikProvider>
    </div>
  );
};

export { ContactAccessRightInput };
