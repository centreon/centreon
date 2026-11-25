import { MenuItem, TextField } from '@mui/material';

import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { path } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { ReasoningMode } from '../models';
import {
  labelReasoningMinimal,
  labelReasoningModelDefault
} from '../translatedLabels';

const options = [
  { label: labelReasoningMinimal, value: ReasoningMode.Minimal },
  { label: labelReasoningModelDefault, value: ReasoningMode.ModelDefault }
];

// The reasoning setting of a model, under its model field. The hidden input
// holds the value and the test id; the visible button opens the options.
const ReasoningField = ({
  dataTestId,
  fieldName,
  getDisabled,
  label
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, setFieldValue } = useFormikContext<Record<string, unknown>>();

  const value =
    (path([fieldName], values) as ReasoningMode | null | undefined) ??
    ReasoningMode.Minimal;

  return (
    <TextField
      disabled={getDisabled?.(values) ?? false}
      fullWidth
      label={t(label)}
      onChange={(event) => setFieldValue(fieldName, event.target.value)}
      select
      size="small"
      slotProps={{
        htmlInput: { 'data-testid': dataTestId },
        select: {
          SelectDisplayProps: {
            'data-testid': `${dataTestId}-select`
          } as Record<string, string>
        }
      }}
      value={value}
    >
      {options.map((option) => (
        <MenuItem key={option.value} value={option.value}>
          {t(option.label)}
        </MenuItem>
      ))}
    </TextField>
  );
};

export default ReasoningField;
