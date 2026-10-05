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

  // A disabled chip only stops the pointer: a click dispatched on it, by
  // assistive technology for one, still reaches this handler.
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
      <div
        className="flex flex-wrap items-center gap-x-6 gap-y-3"
        data-testid={baseTestId}
      >
        {options.map((option) => {
          const isSelected = !isExclusive && includes(option, value);

          return (
            <Chip
              aria-pressed={isSelected}
              // The theme sizes chips for status badges, 12px or 20px tall.
              className="h-6 rounded-3xl text-sm [&_.MuiChip-label]:px-3 [&_.MuiChip-label]:leading-[21px]"
              clickable
              color={isSelected ? 'primary' : 'default'}
              data-testid={`${baseTestId}-${option}`}
              disabled={disabled || isExclusive}
              key={option}
              label={t(option)}
              onClick={toggleChip(option)}
              size="small"
              variant={isSelected ? 'filled' : 'outlined'}
            />
          );
        })}
        {exclusiveToggle}
      </div>
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
