import { useState } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import {
  Dialog,
  ResponseError,
  SingleAutocompleteField,
  SingleConnectedAutocompleteField,
  TextField,
  useResizeObserver
} from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';

import { CreateTokenFormValues } from '../TokenListing/models';
import { getEndpointConfiguredUser } from '../api/endpoints';
import {
  labelCancel,
  labelClose,
  labelDuration,
  labelGenerateNewToken,
  labelName,
  labelUser
} from '../translatedLabels';

import Title from './Title';
import TokenInput from './TokenInput';
import { CreatedToken, dataDuration } from './models';
import { useStyles } from './tokenCreation.styles';
import useCreateTokenFormValues from './useTokenFormValues';
import InputCalendar from './InputCalendar/inputCalendar';

interface Props {
  closeDialog: () => void;
  data?: ResponseError | CreatedToken;
  isDialogOpened: boolean;
  isMutating: boolean;
  isRefetching: boolean;
}

const FormCreation = ({
  data,
  isMutating,
  isRefetching,
  isDialogOpened,
  closeDialog
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { height = 0 } = useResizeObserver<HTMLElement>({
    ref: document.getElementById('root')
  });

  const [isDisplayingDateTimePicker, setIsDisplayingDateTimePicker] =
    useState(false);

  const {
    values,
    isValid,
    dirty,
    handleChange,
    setFieldValue,
    handleSubmit,
    resetForm
  } = useFormikContext<CreateTokenFormValues>();

  const { token, duration, tokenName, user } = useCreateTokenFormValues({
    data,
    values
  });

  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const { canManageApiTokens, isAdmin } = useAtomValue(userAtom);

  const getUsersEndpoint = (): string =>
    platformFeatures?.isCloudPlatform && !isAdmin
      ? getEndpointConfiguredUser({
          search: { regex: { fields: ['is_admin'], value: '0' } }
        })
      : getEndpointConfiguredUser({});

  const close = (): void => {
    resetForm();
    closeDialog();
  };

  const selectCustomizeCase = (value): void => {
    setIsDisplayingDateTimePicker(true);

    if (dayjs(values.duration?.name).isValid()) {
      return;
    }
    setFieldValue('duration', value);
  };

  const changeDuration = (_, value): void => {
    if (equals(value.id, 'customize')) {
      selectCustomizeCase(value);

      return;
    }
    setFieldValue('customizeDate', null);

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

  const confirmDisabled = !dirty || !isValid || isRefetching || isMutating;

  return (
    <Dialog
      cancelDisabled={isMutating}
      confirmDisabled={confirmDisabled}
      data-testid="tokenCreationDialog"
      labelCancel={t(labelCancel)}
      labelConfirm={labelConfirm}
      labelTitle={<Title token={token} />}
      open={isDialogOpened}
      submitting={isMutating}
      onCancel={token ? undefined : close}
      onConfirm={token ? close : handleSubmit}
    >
      <TextField
        autoComplete="off"
        className={classes.input}
        dataTestId="tokenName"
        disabled={Boolean(token)}
        id="tokenName"
        inputProps={{ 'data-testid': 'tokenNameInput' }}
        label={t(labelName)}
        required={!token}
        value={tokenName}
        onChange={handleChange}
      />
      <SingleAutocompleteField
        className={classes.input}
        dataTestId={labelDuration}
        disabled={Boolean(token) || isDisplayingDateTimePicker}
        getOptionItemLabel={(option) => option?.name}
        id="duration"
        label={t(labelDuration)}
        options={options}
        required={!token}
        value={duration}
        onChange={changeDuration}
      />
      {isDisplayingDateTimePicker && (
        <InputCalendar
          setIsDisplayingDateTimePicker={setIsDisplayingDateTimePicker}
          windowHeight={height}
        />
      )}

      <SingleConnectedAutocompleteField
        className={classes.input}
        dataTestId={labelUser}
        disabled={Boolean(token) || !canManageApiTokens}
        field="name"
        getEndpoint={getUsersEndpoint}
        id="user"
        label={t(labelUser)}
        required={!token}
        value={user}
        onChange={changeUser}
      />
      {token && <TokenInput token={token} />}
    </Dialog>
  );
};

export default FormCreation;
