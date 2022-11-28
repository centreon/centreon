import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Switch as MUISwitch } from '@mui/material';

import getNormalizedId from '../../utils/getNormalizedId';
import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const Switch = ({
  dataTestId,
  fieldName,
  change,
  label,
  switchInput,
  getDisabled,
  hideInput,
  additionalMemoProps
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

  const fieldNamePath = split('.', fieldName);

  const value =
    switchInput?.getChecked?.(path(fieldNamePath, values)) ??
    path(fieldNamePath, values);
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
            data-testid={dataTestId}
            disabled={disabled}
            id={getNormalizedId(dataTestId || '')}
            inputProps={{
              'aria-label': t(label)
            }}
            onChange={changeSwitchValue}
          />
        }
        label={t(label) as string}
      />
    ),
    memoProps: [value, disabled, additionalMemoProps, hidden]
  });
};

export default Switch;
