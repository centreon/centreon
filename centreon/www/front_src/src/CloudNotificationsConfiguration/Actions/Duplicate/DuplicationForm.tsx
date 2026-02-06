import { Formik } from 'formik';

import { DuplicateConfirmationDialog, useDuplicate } from '.';
import useValidateName from './useValidateName';

const DuplicationForm = (): JSX.Element => {
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
