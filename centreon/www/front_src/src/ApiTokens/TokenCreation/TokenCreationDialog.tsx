import { Formik } from 'formik';
import { useTranslation } from 'react-i18next';
import { number, object, string } from 'yup';

import { CreateTokenFormValues } from '../TokenListing/models';
import { labelFieldRequired } from '../translatedLabels';

import FormCreation from './Form';
import useCreateToken from './useCreateToken';

const TokenCreationDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { createToken, data, isMutating } = useCreateToken();
  const msgError = t(labelFieldRequired);

  const validationForm = object({
    duration: object({
      id: string().required(),
      name: string().required()
    }).required({ msgError }),
    tokenName: string().required(),
    user: object().shape({
      id: number().required(),
      name: string().required()
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
