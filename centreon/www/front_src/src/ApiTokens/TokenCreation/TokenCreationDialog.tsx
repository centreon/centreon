import { Formik } from 'formik';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { number, object, string } from 'yup';

import { userAtom } from '@centreon/ui-context';

import {
  CreateTokenFormValues,
  PersonalInformation
} from '../TokenListing/models';
import { labelFieldRequired } from '../translatedLabels';
import useRefetch from '../useRefetch';

import FormCreation from './Form';
import { CreatedToken } from './models';
import useCreateToken from './useCreateToken';

interface Props {
  closeDialog: () => void;
  isDialogOpened: boolean;
}

const TokenCreationDialog = ({
  closeDialog,
  isDialogOpened
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { createToken, data, isMutating } = useCreateToken();
  const { isRefetching } = useRefetch({ key: (data as CreatedToken)?.token });
  const currentUser = useAtomValue(userAtom);

  const msgError = t(labelFieldRequired);

  const validationForm = object({
    duration: object({
      id: string().required(),
      name: string().required()
    }).required(msgError),
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
        user: currentUser.canManageApiTokens
          ? null
          : (currentUser as PersonalInformation)
      }}
      validationSchema={validationForm}
      onSubmit={submit}
    >
      <FormCreation
        closeDialog={closeDialog}
        data={data}
        isDialogOpened={isDialogOpened}
        isMutating={isMutating}
        isRefetching={isRefetching}
      />
    </Formik>
  );
};

export default TokenCreationDialog;
