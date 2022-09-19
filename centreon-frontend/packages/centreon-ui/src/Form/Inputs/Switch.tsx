import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { prop } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Switch as MUISwitch } from '@mui/material';

import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const Switch = ({
  fieldName,
  change,
  label,
  switchInput,
  getDisabled,
  hideInput,
  additionalMemoProps,
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const changeSwitchValue = (event: ChangeEvent<HTMLInputElement>): void => {
    if (change) {
      change({ setFieldValue, value: event.target.checked });

      return;
    }

    setFieldValue(fieldName, event.target.checked);
  };

  const value =
    switchInput?.getChecked?.(prop(fieldName, values)) ??
    prop(fieldName, values);
  const disabled = getDisabled?.(values) || false;
  const hidden = hideInput?.(values) || false;

  return useMemoComponent({
    Component: hidden ? (
      <div />
    ) : (
      <FormControlLabel
        control={
          <MUISwitch
            checked={value}
            disabled={disabled}
            inputProps={{ 'aria-label': t(label) }}
            onChange={changeSwitchValue}
          />
        }
        label={t(label) as string}
      />
    ),
    memoProps: [value, disabled, additionalMemoProps, hidden],
  });
};

export default Switch;
