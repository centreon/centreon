import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Switch as MUISwitch } from '@mui/material';

import { useMemoComponent } from '../..';
import { getNormalizedId } from '../../utils/getNormalizedId';

import { InputPropsWithoutGroup } from './models';

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
        setFieldValue,
        value: event.target.checked,
        values,
        setFieldTouched,
        setValues,
        setTouched
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
            slotProps={{
              input: {
                'aria-label': t(label) || ''
              }
            }}
            onChange={changeSwitchValue}
          />
        }
        label={t(label) as string}
      />
    ),
    memoProps: [value, disabled, additionalMemoProps, values]
  });
};

export default Switch;
