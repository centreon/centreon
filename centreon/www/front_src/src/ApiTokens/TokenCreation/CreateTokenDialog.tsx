import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { useFormik } from 'formik';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import { Typography } from '@mui/material';

import {
  Dialog,
  SingleAutocompleteField,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { buildListEndpoint, listConfiguredUser } from '../api/endpoints';
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

  const { createToken, data, isMutating } = useCreateToken();

  const {
    values,
    isValid,
    dirty,
    handleChange,
    setFieldValue,
    handleSubmit,
    resetForm
  } = useFormik<CreateTokenFormValues>({
    initialValues: {
      duration: null,
      tokenName: '',
      user: null
    },
    onSubmit: (dataForm) => {
      const { duration, tokenName, user } = dataForm;

      createToken({ duration, tokenName, user });
    },
    validationSchema: object({
      duration: object({
        id: string().required(),
        name: string().required()
      }).required(),
      tokenName: string().required(),
      user: object({
        id: string().required(),
        name: string().required()
      }).required()
    })
  });

  const { token, duration, tokenName, user } = useCreateTokenFormValues({
    data,
    values
  });

  const getEndpointConfiguredUser = (dataConfiguredUser): string => {
    return buildListEndpoint({
      endpoint: listConfiguredUser,
      parameters: { ...dataConfiguredUser, limit: 10 }
    });
  };

  const closeDialog = (): void => {
    resetForm();
    setIsCreateToken(false);
  };

  const changeDuration = (_, value): void => {
    setFieldValue('duration', value);
  };

  const changeUser = (_, value): void => {
    setFieldValue('user', value);
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
      onConfirm={token ? closeDialog : handleSubmit}
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
        id="tokenName"
        label="Token name"
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={tokenName}
        onChange={handleChange}
      />
      <SingleAutocompleteField
        disabled={Boolean(token)}
        id="duration"
        label="Duration"
        options={options}
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={duration}
        onChange={changeDuration}
      />

      <SingleConnectedAutocompleteField
        disabled={Boolean(token)}
        field="name"
        getEndpoint={getEndpointConfiguredUser}
        id="user"
        label="User"
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={user}
        onChange={changeUser}
      />
      {token && <TokenInput token={token} />}
    </Dialog>
  );
};

export default CreateTokenDialog;
