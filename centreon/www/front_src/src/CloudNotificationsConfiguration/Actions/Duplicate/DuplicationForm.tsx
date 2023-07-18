import { Formik } from 'formik';

import useValidateName from './useValidateName';

import { DuplicateConfirmationDialog, useDuplicate } from '.';

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
