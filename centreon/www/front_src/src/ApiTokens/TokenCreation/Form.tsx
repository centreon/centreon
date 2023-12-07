import { useRef, useState } from 'react';

import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';

import {
  Dialog,
  SingleAutocompleteField,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { buildListEndpoint, listConfiguredUser } from '../api/endpoints';

import CustomTimePeriod from './CustomTimePeriod';
import Title from './Title';
import TokenInput from './TokenInput';
import { isCreateTokenAtom } from './atoms';
import { dataDuration } from './models';
import useCreateTokenFormValues from './useTokenFormValues';

const FormCreation = ({ data, isMutating }): JSX.Element => {
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

  const handleCustomizeCase = (value): void => {
    setIsDisplayingDateTimePicker(true);
    setAnchorEl(refTest?.current);
    setOpen(true);
    setFieldValue('duration', value);
  };

  const changeDuration = (e, value): void => {
    if (value.id === 'customize') {
      handleCustomizeCase(value);

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
      labelTitle={<Title token={token} />}
      open={isCreateToken}
      submitting={isMutating}
      onCancel={token ? undefined : closeDialog}
      onConfirm={token ? closeDialog : handleSubmit}
    >
      <TextField
        dataTestId="tokenNameInput"
        disabled={Boolean(token)}
        label="Name"
        name="tokenName"
        required={!token}
        style={{ marginBottom: 20, width: 450 }}
        value={tokenName}
        onChange={handleChange}
      />
      <SingleAutocompleteField
        disabled={Boolean(token)}
        error={errors?.duration?.invalidDate}
        getOptionItemLabel={(option) => option?.name}
        label="Duration"
        name="duration"
        options={options}
        ref={refTest}
        required={!token}
        style={{ marginBottom: 20, width: 450 }}
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
        label="User"
        name="user"
        required={!token}
        style={{ marginBottom: 20, width: 450 }}
        value={user}
        onChange={changeUser}
      />
      {token && <TokenInput token={token} />}
    </Dialog>
  );
};

export default FormCreation;
