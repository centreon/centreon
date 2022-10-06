import { FormikValues, useFormikContext } from 'formik';
import { equals, includes, path, split } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  FormControlLabel,
  FormGroup,
  FormLabel,
  RadioGroup,
  Radio as MUIRadio,
} from '@mui/material';

import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const Radio = ({
  dataTestId,
  fieldName,
  label,
  radio,
  getDisabled,
  hideInput,
  change,
  additionalMemoProps,
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const changeRadio = (_, value): void => {
    if (includes(value, ['true', 'false'])) {
      if (change) {
        change({ setFieldValue, value: equals(value, 'true') });

        return;
      }

      setFieldValue(fieldName, equals(value, 'true'));

      return;
    }

    if (change) {
      change({ setFieldValue, value });

      return;
    }

    setFieldValue(fieldName, value);
  };

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const disabled = getDisabled?.(values) || false;
  const hidden = hideInput?.(values) || false;

  return useMemoComponent({
    Component: hidden ? (
      <div />
    ) : (
      <FormGroup>
        <FormLabel>{t(label)}</FormLabel>
        <RadioGroup value={value} onChange={changeRadio}>
          {radio?.options?.map(({ value: optionValue, label: optionLabel }) => (
            <FormControlLabel
              control={
                <MUIRadio
                  disabled={disabled}
                  inputProps={{
                    'aria-label': t(optionLabel),
                    'data-testid': dataTestId,
                  }}
                />
              }
              key={optionLabel}
              label={t(optionLabel) as string}
              value={optionValue}
            />
          ))}
        </RadioGroup>
      </FormGroup>
    ),
    memoProps: [value, disabled, additionalMemoProps, hidden],
  });
};

export default Radio;
