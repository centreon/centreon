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
  TextField,
  useResizeObserver
} from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { buildListEndpoint, listConfiguredUser } from '../api/endpoints';
import {
  labelCancel,
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
import { useStyles } from './tokenCreation.styles';

interface Props {
  data?: ResponseError | CreatedToken;
  isMutating: boolean;
}

const FormCreation = ({ data, isMutating }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { height = 0 } = useResizeObserver<HTMLElement>({
    ref: document.getElementById('root')
  });

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
      data-testid="tokenCreationDialog"
      labelCancel={t(labelCancel)}
      labelConfirm={labelConfirm}
      labelTitle={<Title token={token} />}
      open={isCreatingToken}
      submitting={isMutating}
      onCancel={token ? undefined : closeDialog}
      onConfirm={token ? closeDialog : handleSubmit}
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
        error={errors?.duration?.invalidDate}
        getOptionItemLabel={(option) => option?.name}
        id="duration"
        label={t(labelDuration)}
        options={options}
        ref={refSingleAutocompleteField}
        required={!token}
        value={duration}
        onChange={changeDuration}
      />
      {isDisplayingDateTimePicker && (
        <CustomTimePeriod
          anchorElDuration={{ anchorEl, setAnchorEl }}
          openPicker={{ open, setOpen }}
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
