import { useMemo } from 'react';

import dayjs from 'dayjs';
import { useFormik } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';
import relativeTime from 'dayjs/plugin/relativeTime';

import { Typography } from '@mui/material';

import {
  Dialog,
  Method,
  SingleAutocompleteField,
  TextField,
  useLocaleDateTimeFormat,
  useMutationQuery
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { createTokenEndpoint } from '../api/endpoints';
import { labelCreateNewToken } from '../translatedLabels';
import useRefetch from '../useRefetch';

import { isCreateTokenAtom } from './atoms';
import { CreatedToken, Duration, UnitDate } from './models';
import TokenInput from './TokenInput';

dayjs.extend(relativeTime);

const dataDuration: Array<Duration> = [
  { id: '7days', name: '7 days', unit: UnitDate.Day, value: 7 },
  { id: '30days', name: '30 days', unit: UnitDate.Day, value: 30 },
  { id: '60days', name: '60 days', unit: UnitDate.Day, value: 60 },
  { id: '90days', name: '90 days', unit: UnitDate.Day, value: 90 },
  { id: 'oneyear', name: '1 year', unit: UnitDate.Year, value: 1 },
  { id: 'customize', name: 'Customize', unit: null, value: null }
];

const CreateTokenDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { format, toIsoString } = useLocaleDateTimeFormat();
  const [isCreateToken, setIsCreateToken] = useAtom(isCreateTokenAtom);
  const user = useAtomValue(userAtom);

  const { data, mutateAsync } = useMutationQuery<CreatedToken, undefined>({
    getEndpoint: () => createTokenEndpoint,
    method: Method.POST
  });

  useRefetch((data as CreatedToken)?.token);

  const formik = useFormik({
    initialValues: {
      duration: null,
      tokenName: '',
      userName: { id: user.id, name: user.name }
    },
    onSubmit: (values) => {
      const duration = dataDuration.filter(
        ({ id }) => id === values?.duration?.id
      )?.[0];

      const expirationDate = getExpirationDate({
        unit: duration?.unit,
        value: duration?.value
      });

      mutateAsync({
        expiration_date: expirationDate,
        name: values.tokenName,
        user_id: values.userName.id
      });
    },
    validationSchema: object({
      duration: object({
        id: string().required(),
        name: string().required()
      }).required('This field is required'),
      tokenName: string().required('This field is required')
    })
  });

  const getExpirationDate = ({ value, unit }): string => {
    const formattedDate = (format({ date: new Date() }) as dayjs.Dayjs)
      .add(value, unit)
      .toDate();

    return toIsoString(formattedDate);
  };

  const confirm = (): void => {
    formik.handleSubmit();
    formik.setSubmitting(false);
  };

  const closeDialog = (): void => {
    setIsCreateToken(false);
  };

  const changeDuration = (_, value): void => {
    formik.setFieldValue('duration', value);
  };

  const options = dataDuration.map(({ id, name }) => ({
    id,
    name
  }));

  // a modifier
  const { token, durationValue, tokenNameValue } = useMemo(() => {
    const currentData = data as CreatedToken;
    if (!currentData?.token) {
      return {
        durationValue: undefined,
        token: undefined,
        tokenNameValue: undefined
      };
    }
    const { token } = currentData;
    const currentTokenName = currentData.name;

    const endDate = format({
      date: currentData.expiration_date
    }) as dayjs.Dayjs;

    const startDate = format({
      date: currentData.creation_date
    }) as dayjs.Dayjs;

    const numberOfDays = endDate.diff(startDate, UnitDate.Day);

    console.log({ endDate, numberOfDays, startDate });
    if (numberOfDays <= 90) {
      return {
        durationValue: {
          id: `${numberOfDays}days`,
          name: `${numberOfDays} days`
        },
        token,
        tokenNameValue: currentTokenName
      };
    }
    const durationName = startDate.from(endDate, true);

    return {
      durationValue: { id: durationName.trim(), name: durationName },
      token,
      tokenNameValue: currentTokenName
    };
  }, [(data as CreatedToken)?.token]);

  return (
    <Dialog
      confirmDisabled={!formik.dirty || !formik.isValid}
      labelConfirm={token ? 'Close' : 'Generate new Token'}
      labelTitle={token ? 'Token has been created' : t(labelCreateNewToken)}
      open={isCreateToken}
      submitting={formik.isSubmitting}
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
        error={formik.errors?.tokenName}
        helperText={formik.errors?.tokenName}
        id="tokenName"
        label="Token name"
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={token ? tokenNameValue : formik.values.tokenName}
        onChange={formik.handleChange}
      />
      <SingleAutocompleteField
        disabled={Boolean(token)}
        error={formik.errors?.duration}
        id="duration"
        label="Duration"
        options={options}
        required={!token}
        style={{ marginBottom: 20, width: 400 }}
        value={token ? durationValue : formik.values.duration}
        onChange={changeDuration}
      />
      <TextField
        disabled
        dataTestId="userInput"
        id="userName"
        label="User"
        style={{ marginBottom: 20, width: 400 }}
        value={formik.values.userName.name}
      />
      {token && <TokenInput token={token} />}
    </Dialog>
  );
};

export default CreateTokenDialog;
