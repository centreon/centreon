import { Formik } from 'formik';

import { DuplicateConfirmationDialog, useDuplicate } from '.';
import useValidateName from './useValidateName';

const DuplicationForm = (): JSX.Element => {
  const { validationSchema } = useValidateName();
  const { submit } = useDuplicate();

  return (
    <Formik
      initialValues={{ name: '' }}
      validationSchema={validationSchema}
      onSubmit={submit}
    >
      <DuplicateConfirmationDialog />
    </Formik>
  );
};

export default DuplicationForm;
