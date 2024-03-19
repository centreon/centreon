import { useState } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Dialog,
  ResponseError,
  SingleAutocompleteField,
  SingleConnectedAutocompleteField,
  TextField,
  useResizeObserver
} from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { getEndpointConfiguredUser } from '../api/endpoints';
import {
  labelCancel,
  labelClose,
  labelDuration,
  labelGenerateNewToken,
  labelInvalidDateCreationToken,
  labelName,
  labelUser
} from '../translatedLabels';

import CustomTimePeriod from './CustomTimePeriod/CustomTimePeriod';
import Title from './Title';
import TokenInput from './TokenInput';
import { CreatedToken, dataDuration } from './models';
import { useStyles } from './tokenCreation.styles';
import useCreateTokenFormValues from './useTokenFormValues';
import { isInvalidDate } from './utils';

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
    resetForm,
    errors
  } = useFormikContext<CreateTokenFormValues>();

  const { token, duration, tokenName, user } = useCreateTokenFormValues({
    data,
    values
  });

  const close = (): void => {
    resetForm();
    closeDialog();
  };

  const selectCustomizeCase = (value): void => {
    setIsDisplayingDateTimePicker(true);

    if (values.duration?.name) {
      return;
    }
    setFieldValue('duration', value);
  };

  const changeDuration = (_, value): void => {
    if (equals(value.id, 'customize')) {
      selectCustomizeCase(value);

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
  // const dateError = isInvalidDate({ endTime: values.customizeDate })
  //   ? labelInvalidDateCreationToken
  //   : undefined;
  const confirmDisabled = !dirty || !isValid || isRefetching || isMutating;

  console.log({ errors });

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
        error={errors?.duration}
        getOptionItemLabel={(option) => option?.name}
        id="duration"
        label={t(labelDuration)}
        options={options}
        required={!token}
        value={duration}
        onChange={changeDuration}
      />
      {isDisplayingDateTimePicker && (
        <CustomTimePeriod
          setIsDisplayingDateTimePicker={setIsDisplayingDateTimePicker}
          windowHeight={height}
        />
      )}

      <SingleConnectedAutocompleteField
        className={classes.input}
        dataTestId={labelUser}
        disabled={Boolean(token)}
        field="name"
        getEndpoint={getEndpointConfiguredUser}
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
