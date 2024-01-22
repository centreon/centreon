import { ReactNode } from 'react';

import { Formik } from 'formik';
import { useTranslation } from 'react-i18next';
import { number, object, string } from 'yup';

import { ResponseError } from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { labelFieldRequired } from '../translatedLabels';
import useRefetch from '../useRefetch';

import { CreatedToken } from './models';
import useCreateToken from './useCreateToken';

interface Parameters {
  data?: ResponseError | CreatedToken | undefined;
  isMutating: boolean;
  isRefetching: boolean;
}

interface Props {
  renderFormCreation: (params: Parameters) => ReactNode;
}

const TokenCreationDialog = ({ renderFormCreation }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { createToken, data, isMutating } = useCreateToken();
  const { isRefetching } = useRefetch({ key: (data as CreatedToken)?.token });

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
      {renderFormCreation({ data, isMutating, isRefetching })}
    </Formik>
  );
};

export default TokenCreationDialog;
