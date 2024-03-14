import { Formik } from 'formik';

import DuplicateConfirmationDialog from './DuplicateConfirmationDialog';
import useValidateName from './useValidateName';
import useDuplicate from './useDuplicate';

const DuplicationForm = (): React.JSX.Element => {
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
