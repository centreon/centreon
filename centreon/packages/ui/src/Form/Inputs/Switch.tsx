import { FormControlLabel, Switch as MUISwitch } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';
import type { ChangeEvent } from 'react';
import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '../..';
import { getNormalizedId } from '../../utils/getNormalizedId';
import type { InputPropsWithoutGroup } from './models';

const Switch = ({
  dataTestId,
  fieldName,
  change,
  label,
  switchInput,
  getDisabled,
  additionalMemoProps
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched, setValues, setTouched } =
    useFormikContext<FormikValues>();

  const changeSwitchValue = (event: ChangeEvent<HTMLInputElement>): void => {
    if (change) {
      change({
        setFieldTouched,
        setFieldValue,
        setTouched,
        setValues,
        value: event.target.checked,
        values
      });

      return;
    }

    setFieldValue(fieldName, event.target.checked);
  };

  const fieldNamePath = split('.', fieldName);

  const value =
    switchInput?.getChecked?.(path(fieldNamePath, values)) ??
    path(fieldNamePath, values);
  const disabled = getDisabled?.(values) || false;

  return useMemoComponent({
    Component: (
      <FormControlLabel
        control={
          <MUISwitch
            checked={value}
            data-testid={dataTestId}
            disabled={disabled}
            id={getNormalizedId(dataTestId || '')}
            onChange={changeSwitchValue}
            slotProps={{
              input: {
                'aria-label': t(label) || ''
              }
            }}
          />
        }
        label={t(label) as string}
      />
    ),
    memoProps: [value, disabled, additionalMemoProps, values]
  });
};

export default Switch;
