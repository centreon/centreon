import { useFormikContext } from 'formik';
import { equals, find } from 'ramda';
import type React from 'react';
import { useTranslation } from 'react-i18next';

import { SelectEntry, SelectField } from '@centreon/ui';

import {
  RequestedAuthnContextComparisonValue,
  SAMLConfiguration
} from '../models';
import {
  labelBetter,
  labelExact,
  labelMaximum,
  labelMinimum,
  labelRequestedAuthnContextComparison
} from '../translatedLabels';

const RequestedAuthnContextComparisonField = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { values, setFieldValue, errors, touched } =
    useFormikContext<SAMLConfiguration>();

  const changeValue = (event): void => {
    setFieldValue('requestedAuthnContextComparison', event.target.value);
  };

  const options: Array<SelectEntry> = [
    { id: RequestedAuthnContextComparisonValue.Minimum, name: t(labelMinimum) },
    { id: RequestedAuthnContextComparisonValue.Exact, name: t(labelExact) },
    { id: RequestedAuthnContextComparisonValue.Better, name: t(labelBetter) },
    { id: RequestedAuthnContextComparisonValue.Maximum, name: t(labelMaximum) }
  ];

  const selectedOption = find((option: SelectEntry) =>
    equals(option.id, values.requestedAuthnContextComparison)
  )(options);

  const error = touched?.requestedAuthnContextComparison
    ? errors?.requestedAuthnContextComparison
    : undefined;

  return (
    <SelectField
      fullWidth
      required
      aria-label={labelRequestedAuthnContextComparison}
      dataTestId={labelRequestedAuthnContextComparison}
      error={error as string}
      label={labelRequestedAuthnContextComparison}
      name="requestedAuthnContextComparison"
      options={options}
      selectedOptionId={
        selectedOption?.id || RequestedAuthnContextComparisonValue.Minimum
      }
      onChange={changeValue}
    />
  );
};

export default RequestedAuthnContextComparisonField;
