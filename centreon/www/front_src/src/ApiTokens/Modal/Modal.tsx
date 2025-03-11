import { Formik } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { number, object, string } from 'yup';

import { userAtom } from '@centreon/ui-context';

import { CreateTokenFormValues, NamedEntity } from '../Listing/models';
import { labelFieldRequired } from '../translatedLabels';

import { useSearchParams } from 'react-router';
import { ModalStateAtom } from '../atoms';
import FormCreation from './Form';
import useCreateToken from './useCreateToken';

const Modal = (): JSX.Element => {
  const { t } = useTranslation();
  const { createToken, data } = useCreateToken();
  const currentUser = useAtomValue(userAtom);

  const [, setSearchParams] = useSearchParams();
  const [modalState, setModalState] = useAtom(ModalStateAtom);

  const closeDialog = () => {
    setSearchParams({});

    setModalState({ ...modalState, isOpen: false });
  };

  const validationForm = object({
    duration: object({
      id: string().required(),
      name: string().required()
    }).required(t(labelFieldRequired)),
    tokenName: string().required(),
    user: object().shape({
      id: number().required(),
      name: string().required()
    })
  });

  return (
    <Formik<CreateTokenFormValues>
      initialValues={{
        customizeDate: null,
        duration: null,
        tokenName: '',
        user: currentUser.canManageApiTokens
          ? null
          : (currentUser as NamedEntity)
      }}
      validationSchema={validationForm}
      onSubmit={createToken}
    >
      <FormCreation
        closeDialog={closeDialog}
        data={data}
        isDialogOpened={modalState.isOpen}
      />
    </Formik>
  );
};

export default Modal;
