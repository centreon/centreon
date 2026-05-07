import { Formik } from 'formik';

import { DuplicateConfirmationDialog, useDuplicate } from '.';
import useValidateName from './useValidateName';
import { ReactElement } from 'react';

const DuplicationForm = (): ReactElement => {
  const { validationSchema } = useValidateName();
  const { submit } = useDuplicate();

  return (
    <Formik
      initialValues={{ name: '' }}
      onSubmit={submit}
      validationSchema={validationSchema}
    >
      <DuplicateConfirmationDialog />
    </Formik>
  );
};

export default DuplicationForm;
