import { useRef, useState } from 'react';

import { equals } from 'ramda';
import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  Dialog,
  ResponseError,
  SingleAutocompleteField,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { buildListEndpoint, listConfiguredUser } from '../api/endpoints';
import {
  labelClose,
  labelDuration,
  labelGenerateNewToken,
  labelName,
  labelUser
} from '../translatedLabels';

import CustomTimePeriod from './CustomTimePeriod/CustomTimePeriod';
import Title from './Title';
import TokenInput from './TokenInput';
import { CreatedToken, dataDuration } from './models';
import useCreateTokenFormValues from './useTokenFormValues';
import { isCreatingTokenAtom } from './atoms';

interface Props {
  data?: ResponseError | CreatedToken;
  isMutating: boolean;
}

const FormCreation = ({ data, isMutating }: Props): JSX.Element => {
  const { t } = useTranslation();
  const [open, setOpen] = useState(true);
  const [isDisplayingDateTimePicker, setIsDisplayingDateTimePicker] =
    useState(false);
  const refSingleAutocompleteField = useRef<HTMLDivElement | null>(null);
  const [anchorEl, setAnchorEl] = useState<HTMLDivElement | null>(null);

  const [isCreatingToken, setIsCreatingToken] = useAtom(isCreatingTokenAtom);

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
    setIsCreatingToken(false);
  };

  const handleCustomizeCase = (value): void => {
    setIsDisplayingDateTimePicker(true);
    setAnchorEl(refSingleAutocompleteField?.current);
    setOpen(true);
    setFieldValue('duration', value);
  };

  const changeDuration = (_, value): void => {
    if (equals(value.id, 'customize')) {
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

  const labelConfirm = token ? t(labelClose) : t(labelGenerateNewToken);

  return (
    <Dialog
      confirmDisabled={!dirty || !isValid}
      labelConfirm={labelConfirm}
      labelTitle={<Title token={token} />}
      open={isCreatingToken}
      submitting={isMutating}
      onCancel={token ? undefined : closeDialog}
      onConfirm={token ? closeDialog : handleSubmit}
    >
      <TextField
        dataTestId="tokenNameInput"
        disabled={Boolean(token)}
        id="tokenName"
        label={t(labelName)}
        required={!token}
        style={{ marginBottom: 20, width: 450 }}
        value={tokenName}
        onChange={handleChange}
      />
      <SingleAutocompleteField
        disabled={Boolean(token) || isDisplayingDateTimePicker}
        error={errors?.duration?.invalidDate}
        getOptionItemLabel={(option) => option?.name}
        id="duration"
        label={t(labelDuration)}
        options={options}
        ref={refSingleAutocompleteField}
        required={!token}
        style={{ marginBottom: 20, width: 450 }}
        value={duration}
        onChange={changeDuration}
      />
      {isDisplayingDateTimePicker && (
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
        label={t(labelUser)}
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
