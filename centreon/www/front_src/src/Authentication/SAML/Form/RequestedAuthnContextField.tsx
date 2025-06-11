import type React from 'react';

import { useFormikContext } from 'formik';
import { equals, find } from 'ramda';
import { useTranslation } from 'react-i18next';

import { SelectEntry, SelectField } from '@centreon/ui';

import { RequestedAuthnContextValue, SAMLConfiguration } from '../models';
import {
  labelBetter,
  labelExact,
  labelMaximum,
  labelMinimum,
  labelRequestedAuthnContext
} from '../translatedLabels';

const RequestedAuthnContextField = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { values, setFieldValue, errors, touched } =
    useFormikContext<SAMLConfiguration>();

  const changeValue = (event): void => {
    setFieldValue('requestedAuthnContext', event.target.value);
  };

  const options: Array<SelectEntry> = [
    { id: RequestedAuthnContextValue.Minimum, name: t(labelMinimum) },
    { id: RequestedAuthnContextValue.Exact, name: t(labelExact) },
    { id: RequestedAuthnContextValue.Better, name: t(labelBetter) },
    { id: RequestedAuthnContextValue.Maximum, name: t(labelMaximum) }
  ];

  const selectedOption = find((option: SelectEntry) =>
    equals(option.id, values.requestedAuthnContext)
  )(options);

  const error = touched?.requestedAuthnContext
    ? errors?.requestedAuthnContext
    : undefined;

  return (
    <SelectField
      fullWidth
      required
      aria-label={labelRequestedAuthnContext}
      dataTestId={labelRequestedAuthnContext}
      error={error as string}
      label={labelRequestedAuthnContext}
      name="requestedAuthnContext"
      options={options}
      selectedOptionId={
        selectedOption?.id || RequestedAuthnContextValue.Minimum
      }
      onChange={changeValue}
    />
  );
};

export default RequestedAuthnContextField;
