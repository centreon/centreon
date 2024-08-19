import { FormikHelpers, FormikValues } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { putData, useRequest, useSnackbar } from '@centreon/ui';

import { labelLoginSucceeded } from '../Login/translatedLabels';
import usePostLogin from '../Login/usePostLogin';
import useUser from '../Main/useUser';

import { object, string } from 'yup';
import { getResetPasswordEndpoint } from './api/endpoint';
import { ResetPasswordValues } from './models';
import { passwordResetInformationsAtom } from './passwordResetInformationsAtom';
import {
  labelNewPasswordsMustMatch,
  labelPasswordRenewed,
  labelRequired,
  labelTheNewPasswordIstheSameAsTheOldPassword
} from './translatedLabels';

interface UseResetPasswordState {
  submitResetPassword: (
    values: ResetPasswordValues,
    { setSubmitting }: Pick<FormikHelpers<FormikValues>, 'setSubmitting'>
  ) => void;
  validationSchema: Yup.SchemaOf<ResetPasswordValues>;
}

function matchNewPasswords(this, newConfirmationPassword?: string): boolean {
  return equals(newConfirmationPassword, this.parent.newPassword);
}

function differentPasswords(this, newPassword?: string): boolean {
  return not(equals(newPassword, this.parent.oldPassword));
}

export const router = {
  useNavigate
};

const useResetPassword = (): UseResetPasswordState => {
  const { t } = useTranslation();
  const navigate = router.useNavigate();

  const { showSuccessMessage } = useSnackbar();
  const { sendRequest } = useRequest({
    request: putData
  });

  const passwordResetInformations = useAtomValue(passwordResetInformationsAtom);

  const loadUser = useUser();
  const { sendLogin } = usePostLogin();

  const submitResetPassword = (
    values: ResetPasswordValues,
    { setSubmitting }: Pick<FormikHelpers<FormikValues>, 'setSubmitting'>
  ): void => {
    sendRequest({
      data: {
        new_password: values.newPassword,
        old_password: values.oldPassword
      },
      endpoint: getResetPasswordEndpoint(
        passwordResetInformations?.alias as string
      )
    })
      .then(() => {
        showSuccessMessage(t(labelPasswordRenewed));
        sendLogin({
          payload: {
            login: passwordResetInformations?.alias as string,
            password: values.newPassword
          }
        }).then(({ redirectUri }) => {
          showSuccessMessage(t(labelLoginSucceeded));
          loadUser()?.then(() => navigate(redirectUri));
        });
      })
      .catch(() => {
        setSubmitting(false);
      });
  };

  const validationSchema = object().shape({
    newPassword: string()
      .test(
        'match',
        t(labelTheNewPasswordIstheSameAsTheOldPassword),
        differentPasswords
      )
      .required(t(labelRequired)),
    newPasswordConfirmation: string()
      .test('match', t(labelNewPasswordsMustMatch), matchNewPasswords)
      .required(t(labelRequired)),
    oldPassword: string().required(t(labelRequired))
  });

  return {
    submitResetPassword,
    validationSchema
  };
};

export default useResetPassword;
