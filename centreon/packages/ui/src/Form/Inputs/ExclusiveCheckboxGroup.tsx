import { Box, Chip } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { equals, includes, path, reject, split } from 'ramda';
import type { ChangeEvent } from 'react';
import { useTranslation } from 'react-i18next';

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
  const { t } = useTranslation();
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

  const toggleChip = (option: string) => (): void => {
    if (disabled || isExclusive) {
      return;
    }

    setFieldValue(
      fieldName,
      includes(option, value)
        ? reject(equals(option), value)
        : [...value, option]
    );
  };

  const baseTestId = dataTestId || fieldName;

  const isChips = equals(exclusiveCheckboxGroup?.variant, 'chips');

  const exclusiveToggle = (
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
  );

  return useMemoComponent({
    Component: isChips ? (
      <Box
        data-testid={baseTestId}
        sx={{
          alignItems: 'center',
          columnGap: 3,
          display: 'flex',
          flexWrap: 'wrap',
          rowGap: 1.5
        }}
      >
        {options.map((option) => {
          const isSelected = !isExclusive && includes(option, value);

          return (
            <Chip
              aria-pressed={isSelected}
              clickable
              color={isSelected ? 'primary' : 'default'}
              data-testid={`${baseTestId}-${option}`}
              disabled={disabled || isExclusive}
              key={option}
              label={t(option)}
              onClick={toggleChip(option)}
              size="small"
              // The theme sizes chips for status badges, 12px or 20px tall.
              sx={{
                '& .MuiChip-label': { lineHeight: '21px', px: 1.5 },
                borderRadius: 3,
                fontSize: 14,
                height: 24
              }}
              variant={isSelected ? 'filled' : 'outlined'}
            />
          );
        })}
        {exclusiveToggle}
      </Box>
    ) : (
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
        {exclusiveToggle}
      </Box>
    ),
    memoProps: [
      value,
      disabled,
      options,
      exclusiveOption,
      exclusiveCheckboxGroup?.exclusiveLabel,
      exclusiveCheckboxGroup?.direction,
      exclusiveCheckboxGroup?.labelPlacement,
      exclusiveCheckboxGroup?.variant
    ]
  });
};

export default ExclusiveCheckboxGroup;
