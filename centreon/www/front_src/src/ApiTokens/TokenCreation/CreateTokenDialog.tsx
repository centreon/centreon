import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { useFormik } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import { Typography } from '@mui/material';

import { Dialog, SingleAutocompleteField, TextField } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { CreateTokenFormValues } from '../TokenListing/models';
import { labelCreateNewToken } from '../translatedLabels';

import TokenInput from './TokenInput';
import { isCreateTokenAtom } from './atoms';
import { dataDuration } from './models';
import useCreateToken from './useCreateToken';
import useCreateTokenFormValues from './useCreateTokenFormValues';

dayjs.extend(relativeTime);

const CreateTokenDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const [isCreateToken, setIsCreateToken] = useAtom(isCreateTokenAtom);
  const user = useAtomValue(userAtom);

  const { createToken, data, isMutating } = useCreateToken();

  const {
    values,
    isValid,
    dirty,
    errors,
    handleChange,
    setFieldValue,
    handleSubmit,
    resetForm
  } = useFormik<CreateTokenFormValues>({
    initialValues: {
      duration: null,
      token: null,
      tokenName: '',
      userName: { id: user.id as number, name: user.name }
    },
    onSubmit: (dataForm) => {
      const {
        duration: durationData,
        tokenName: tokenNameData,
        userName: userData
      } = dataForm;

      createToken({ durationData, tokenNameData, userData });
    },
    validationSchema: object({
      duration: object({
        id: string().required(),
        name: string().required()
      }).required('This field is required'),
      tokenName: string().required('This field is required')
    })
  });

  const { token, durationValue, tokenNameValue } = useCreateTokenFormValues({
    data,
    values
  });

  const confirm = (): void => {
    handleSubmit();
  };

  const closeDialog = (): void => {
    resetForm();
    setIsCreateToken(false);
  };

  const changeDuration = (_, value): void => {
    setFieldValue('duration', value);
  };

  const options = dataDuration.map(({ id, name }) => ({
    id,
    name
  }));

  return (
    <Dialog
      confirmDisabled={!dirty || !isValid}
      labelConfirm={token ? 'Close' : 'Generate new Token'}
      labelTitle={token ? 'Token has been created' : t(labelCreateNewToken)}
      open={isCreateToken}
      submitting={isMutating}
      onCancel={token ? undefined : closeDialog}
      onConfirm={token ? closeDialog : confirm}
    >
      {token && (
        <Typography style={{ marginBottom: 20, width: 400 }}>
          For security reasons, the token can only be displayed once.Remember to
          store it well.
        </Typography>
      )}
      <TextField
        dataTestId="tokenNameInput"
        disabled={Boolean(token)}
        error={errors?.tokenName}
        helperText={errors?.tokenName}
        id="tokenName"
        label="Token name"
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={tokenNameValue}
        onChange={handleChange}
      />
      <SingleAutocompleteField
        disabled={Boolean(token)}
        error={errors?.duration}
        id="duration"
        label="Duration"
        options={options}
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={durationValue}
        onChange={changeDuration}
      />
      <TextField
        disabled
        dataTestId="userInput"
        id="userName"
        label="User"
        style={{ marginBottom: 20, width: 400 }}
        value={values.userName.name}
      />
      {token && <TokenInput token={token} />}
    </Dialog>
  );
};

export default CreateTokenDialog;
