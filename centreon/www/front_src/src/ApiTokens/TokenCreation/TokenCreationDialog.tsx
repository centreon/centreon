import { Formik } from 'formik';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { CreateTokenFormValues } from '../TokenListing/models';
import {
  labelInvalidDateCreationToken,
  labelRequired
} from '../translatedLabels';

import FormCreation from './Form';
import useCreateToken from './useCreateToken';
import { isInvalidDate } from './utils';
import { ErrorForm, ErrorKeys } from './models';

const TokenCreationDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { createToken, data, isMutating } = useCreateToken();

  const validationForm = (values): Record<ErrorKeys, ErrorForm> => {
    const { duration, tokenName, user, customizeDate } = values;
    const keys = ['id', 'name'];
    const msg = { msg: t(labelRequired) };
    let errors: Record<ErrorKeys, ErrorForm> | Record<string, never> = {};

    const isUserError = keys.some((key) => !(key in user));
    const isDurationError = keys.some((key) => !(key in duration));

    if (isUserError) {
      errors = { ...errors, user: msg } as Record<ErrorKeys, ErrorForm>;
    }
    if (!tokenName) {
      errors = { ...errors, tokenName: msg } as Record<ErrorKeys, ErrorForm>;
    }
    if (isDurationError) {
      errors = { ...errors, duration: msg } as Record<ErrorKeys, ErrorForm>;
    }

    if (equals(duration?.id, 'customize')) {
      if (!isInvalidDate({ endTime: customizeDate })) {
        return errors;
      }
      errors = {
        ...errors,
        duration: {
          ...errors?.duration,
          invalidDate: t(labelInvalidDateCreationToken)
        }
      } as Record<ErrorKeys, ErrorForm>;
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
