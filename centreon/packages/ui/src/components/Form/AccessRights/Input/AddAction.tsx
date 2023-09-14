import { ReactElement } from 'react';

import { useFormikContext } from 'formik';

import { Add as AddIcon } from '@mui/icons-material';

import { ContactAccessRightResource } from '../AccessRights.resource';
import { IconButton } from '../../../Button';

type AddActionProps = {
  label?: string;
};

const AddAction = ({ label }: AddActionProps): ReactElement => {
  const { dirty, isValid, submitForm } =
    useFormikContext<Partial<ContactAccessRightResource>>();

  return (
    <IconButton
      aria-label={label}
      data-testid="add"
      disabled={!dirty || !isValid}
      icon={<AddIcon />}
      size="medium"
      variant="primary"
      onClick={submitForm}
    />
  );
};

export { AddAction };
