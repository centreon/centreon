import { Formik } from 'formik';
import { useAtomValue } from 'jotai';

import { duplicatedRuleAtom } from '../../atom';

import DuplicateConfirmationDialog from './DuplicateConfirmationDialog';
import useDuplicate from './useDuplicate';
import useValidateName from './useValidateName';

const DuplicationForm = (): React.JSX.Element => {
  const { validationSchema } = useValidateName();
  const { submit } = useDuplicate();
  const duplicatedRule = useAtomValue(duplicatedRuleAtom);

  return (
    <Formik
      enableReinitialize
      initialValues={{ name: `${duplicatedRule.rule?.name}_1` }}
      validationSchema={validationSchema}
      onSubmit={submit}
    >
      <DuplicateConfirmationDialog />
    </Formik>
  );
};

export default DuplicationForm;
