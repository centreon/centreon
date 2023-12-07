import { useRef, useState } from 'react';

import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

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

import CustomTimePeriod from './CustomTimePeriod';
import TokenInput from './TokenInput';
import { isCreateTokenAtom } from './atoms';
import { dataDuration } from './models';
import useCreateTokenFormValues from './useTokenFormValues';

const FormCreation = ({ data, isMutating }): JSX.Element => {
  const { t } = useTranslation();

  const [isDisplayingDaTimePicker, setIsDisplayingDateTimePicker] =
    useState(false);

  const [anchorEl, setAnchorEl] = useState(null);
  const [open, setOpen] = useState(true);

  const refTest = useRef();

  const [isCreateToken, setIsCreateToken] = useAtom(isCreateTokenAtom);

  const {
    values,
    isValid,
    dirty,
    handleChange,
    setFieldValue,
    handleSubmit,
    resetForm,
    errors
  } = useFormikContext<CreateTokenFormValues>();

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

  const changeDuration = (e, value): void => {
    if (value.id === 'customize') {
      setIsDisplayingDateTimePicker(true);
      setAnchorEl(refTest?.current);
      setOpen(true);
      setFieldValue('duration', value);

      return;
    }

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
      labelTitle={token ? `Token has been created` : t(labelCreateNewToken)}
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
        error={errors?.duration?.invalidDate}
        getOptionItemLabel={(option) => option?.name}
        id="duration"
        label="Duration"
        options={options}
        ref={refTest}
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={duration}
        onChange={changeDuration}
      />
      {isDisplayingDaTimePicker && (
        <CustomTimePeriod
          anchorElDuration={{ anchorEl, setAnchorEl }}
          openPicker={{ open, setOpen }}
          setIsDisplayingDateTimePicker={setIsDisplayingDateTimePicker}
        />
      )}

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

export default FormCreation;
