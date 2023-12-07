import { Formik } from 'formik';

import { CreateTokenFormValues } from '../TokenListing/models';

import FormCreation from './Form';
import useCreateToken from './useCreateToken';
import { isInvalidDate } from './utils';

const TokenCreationDialog = (): JSX.Element => {
  const { createToken, data, isMutating } = useCreateToken();

  const validationForm = (values) => {
    const { duration, tokenName, user, customizeDate } = values;
    let errors = {};
    if (!user?.id || !user?.name) {
      errors = { ...errors, user: { msg: 'required' } };
    }
    if (!tokenName) {
      errors = { ...errors, tokenName: { msg: 'required' } };
    }
    if (!duration?.id || !duration.name) {
      errors = { ...errors, duration: { msg: 'required' } };
    }
    if (duration?.id === 'customize') {
      if (!isInvalidDate({ endTime: customizeDate })) {
        return errors;
      }
      errors = {
        ...errors,
        duration: {
          ...errors?.duration,
          invalidDate: 'The end date must be greater than the actual date'
        }
      };
    }

    return errors;
  };

  const submit = (dataForm): void => {
    const { duration, tokenName, user, customizeDate } = dataForm;

    createToken({ customizeDate, duration, tokenName, user });
  };

  return (
    <Formik<CreateTokenFormValues>
      initialValues={{
        customizeDate: null,
        duration: null,
        tokenName: '',
        user: null
      }}
      validate={validationForm}
      onSubmit={submit}
    >
      <FormCreation data={data} isMutating={isMutating} />
    </Formik>
  );
};

export default TokenCreationDialog;
