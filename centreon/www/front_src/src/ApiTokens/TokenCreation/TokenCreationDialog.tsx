import { Formik } from 'formik';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';
import { object, string, number } from 'yup';

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
  const msgError = t(labelRequired);

  const validationForm = object({
    duration: object({
      id: string().required(msgError),
      name: string().required(msgError)
    }),
    tokenName: string().required(msgError),
    user: object({
      id: number().required(msgError),
      name: string().required(msgError)
    })
  });

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
      validationSchema={validationForm}
      onSubmit={submit}
    >
      <FormCreation data={data} isMutating={isMutating} />
    </Formik>
  );
};

export default TokenCreationDialog;
