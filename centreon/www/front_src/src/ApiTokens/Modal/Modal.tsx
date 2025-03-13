import { Formik } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { number, object, string } from 'yup';

import { userAtom } from '@centreon/ui-context';

import { CreateTokenFormValues, NamedEntity } from '../Listing/models';
import { labelRequired } from '../translatedLabels';

import { equals } from 'ramda';
import { useSearchParams } from 'react-router';
import { ModalStateAtom } from '../atoms';
import { TokenType } from '../models';
import FormCreation from './Form';
import useCreateToken from './useCreateToken';
import { tokenTypes } from './utils';

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
    }).required(t(labelRequired)),
    tokenName: string().required(),
    type: object({
      id: string().required(),
      name: string().required()
    }).required(t(labelRequired)),
    user: object().when('type', ([type], schema) => {
      return equals(type.id, TokenType.API)
        ? schema
            .shape({
              id: number().required(),
              name: string().required()
            })
            .required(t(labelRequired))
        : schema.nullable();
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
          : (currentUser as NamedEntity),
        type: tokenTypes[0]
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
