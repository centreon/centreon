import type React from 'react';
import { useFormikContext } from 'formik';
import { equals, find } from 'ramda';

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
    { id: RequestedAuthnContextValue.Minimum, name: 'Minimum' },
    { id: RequestedAuthnContextValue.Exact, name: 'Exact' },
    { id: RequestedAuthnContextValue.Better, name: 'Better' },
    { id: RequestedAuthnContextValue.Maximum, name: 'Maximum' },
  ];

  const selectedOption = find(
    (option: SelectEntry) => equals(option.id, values.requestedAuthnContext)
  )(options);

  const error = touched?.requestedAuthnContext
    ? errors?.requestedAuthnContext
    : undefined

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
        selectedOption?.id
          || RequestedAuthnContextValue.Minimum
      }
      onChange={changeValue}
    />
  );
}

export default RequestedAuthnContextField;
