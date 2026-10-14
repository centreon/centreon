import { Box } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { equals, includes, path, reject, split } from 'ramda';
import type { ChangeEvent } from 'react';

import { useMemoComponent } from '../..';
import { CheckboxGroup as CheckboxGroupComponent } from '../../Checkbox';
import {
  type ChangeArgs,
  type InputPropsWithoutGroup,
  InputType
} from './models';
import Switch from './Switch';

const ExclusiveCheckboxGroup = ({
  exclusiveCheckboxGroup,
  fieldName,
  getDisabled,
  dataTestId
}: InputPropsWithoutGroup): JSX.Element => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = (path(fieldNamePath, values) as Array<string> | null) ?? [];

  const exclusiveOption = exclusiveCheckboxGroup?.exclusiveOption as string;
  const options = reject(
    equals(exclusiveOption),
    exclusiveCheckboxGroup?.options ?? []
  );

  const isExclusive = includes(exclusiveOption, value);
  const disabled = getDisabled?.(values) || false;

  const changeToggle = ({ value: checked }: ChangeArgs): void => {
    setFieldValue(fieldName, checked ? [exclusiveOption] : []);
  };

  const changeCheckbox = (event: ChangeEvent<HTMLInputElement>): void => {
    if (disabled || isExclusive) {
      return;
    }

    const option = event.target.id;

    setFieldValue(
      fieldName,
      includes(option, value)
        ? reject(equals(option), value)
        : [...value, option]
    );
  };

  const baseTestId = dataTestId || fieldName;

  return useMemoComponent({
    Component: (
      <Box sx={{ alignItems: 'center', columnGap: 2, display: 'flex' }}>
        <CheckboxGroupComponent
          dataTestId={baseTestId}
          direction={exclusiveCheckboxGroup?.direction}
          disabled={disabled || isExclusive}
          labelPlacement={exclusiveCheckboxGroup?.labelPlacement || 'end'}
          onChange={changeCheckbox}
          options={options}
          values={isExclusive ? [] : value}
        />
        <Switch
          change={changeToggle}
          dataTestId={`${baseTestId}-exclusive`}
          fieldName={fieldName}
          getDisabled={() => disabled}
          label={exclusiveCheckboxGroup?.exclusiveLabel as string}
          switchInput={{
            getChecked: (fieldValue) =>
              includes(exclusiveOption, (fieldValue as Array<string>) ?? [])
          }}
          type={InputType.Switch}
        />
      </Box>
    ),
    memoProps: [
      value,
      disabled,
      options,
      exclusiveOption,
      exclusiveCheckboxGroup?.exclusiveLabel,
      exclusiveCheckboxGroup?.direction,
      exclusiveCheckboxGroup?.labelPlacement
    ]
  });
};

export default ExclusiveCheckboxGroup;
