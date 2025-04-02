import type React from 'react';
import { useFormikContext } from 'formik';
import { equals, find, map } from 'ramda';

import { SelectEntry, SelectField } from '@centreon/ui';

import { labelRequestedAuthnContext } from '../translatedLabels';
import { RequestedAuthnContextValue, SAMLConfiguration } from '../models';
import { capitalize } from '@mui/material';

const RequestedAuthnContextField = (): React.JSX.Element => {
  const { values, setFieldValue, errors, touched } =
    useFormikContext<SAMLConfiguration>();

  console.log(values.requestedAuthnContext);

  const changeValue = (event): void => {
    setFieldValue('requestedAuthnContext', event.target.value);
  };

  const options: Array<SelectEntry> = [
    { id: 1, name: RequestedAuthnContextValue.Minimum },
    { id: 2, name: RequestedAuthnContextValue.Exact },
    { id: 3, name: RequestedAuthnContextValue.Better },
    { id: 4, name: RequestedAuthnContextValue.Maximum },
  ];

  const selectedOption = find(
    (option: SelectEntry) => equals(option.name, values.requestedAuthnContext)
  )(options);

  const capitalizedOptions = map(
    (option: SelectEntry) => ({ ...option, name: capitalize(option.name)}),
    options
  ); 

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
      options={capitalizedOptions}
      selectedOptionId={selectedOption?.id || 1}
      onChange={changeValue}
    />
  );
}

export default RequestedAuthnContextField;
