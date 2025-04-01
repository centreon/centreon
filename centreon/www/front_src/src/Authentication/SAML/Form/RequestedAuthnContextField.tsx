import type React from 'react';
import { useFormikContext } from 'formik';

import { SelectEntry, SelectField } from '@centreon/ui';

import { labelRequestedAuthnContext } from '../translatedLabels';
import { RequestedAuthnContextValue, SAMLConfiguration } from '../models';

const RequestedAuthnContextField = (): React.JSX.Element => {
  const { values, setFieldValue, errors, touched } =
    useFormikContext<SAMLConfiguration>();

  const changeValue = (event): void => {
    setFieldValue('requestedAuthnContext', event.target.value);
  };

  const options: Array<SelectEntry> = [
    { id: 1, name: RequestedAuthnContextValue.Minimum.toUpperCase() },
    { id: 2, name: RequestedAuthnContextValue.Exact.toUpperCase() },
    { id: 3, name: RequestedAuthnContextValue.Better.toUpperCase() },
    { id: 4, name: RequestedAuthnContextValue.Maximum.toUpperCase() },
  ];

  const error = touched?.requestedAuthnContext
    ? errors?.requestedAuthnContext
    : undefined

  return (
    <SelectField
      fullWidth
      required
      dataTestId={labelRequestedAuthnContext}
      error={error as string}
      label={labelRequestedAuthnContext}
      name="requestedAuthnContext"
      options={options}
      selectedOptionId={values.requestedAuthnContext || 1}
      onChange={changeValue}
    />
  );
}

export default RequestedAuthnContextField;
