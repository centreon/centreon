import LockIcon from '@mui/icons-material/Lock';
import LockOpenIcon from '@mui/icons-material/LockOpen';
import { Typography } from '@mui/material';

import { type InputPropsWithoutGroup, TextField } from '@centreon/ui';
import {
  type RenderRowParams,
  SortableEntriesList
} from '@centreon/ui/components';

import { type FormikValues, getIn, useFormikContext } from 'formik';
import type { ChangeEvent, ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelAddNewEntry,
  labelDescription,
  labelName,
  labelPassword,
  labelValue
} from '../translatedLabels';

export interface MacroRow {
  description: string;
  // A password the server keeps but never sends back: left empty, it is kept.
  hasStoredPassword: boolean;
  isPassword: boolean;
  name: string;
  value: string;
}

const maxVisibleRows = 10;

const createMacroRow = (): MacroRow => ({
  description: '',
  hasStoredPassword: false,
  isPassword: false,
  name: '',
  value: ''
});

// The host's own macros, `$_HOST<NAME>$`, in order.
const Macros = ({
  dataTestId,
  fieldName,
  getDisabled,
  label
}: InputPropsWithoutGroup): ReactElement => {
  const { t } = useTranslation();
  const {
    errors,
    setFieldTouched,
    setFieldValue,
    submitCount,
    touched,
    values
  } = useFormikContext<FormikValues>();

  const macros = (getIn(values, fieldName) ?? []) as Array<MacroRow>;
  const disabled = !!getDisabled?.(values);

  const getError = (index: number, key: keyof MacroRow): string | undefined => {
    const path = `${fieldName}.${index}.${key}`;
    const isTouched = submitCount > 0 || !!getIn(touched, path);

    return isTouched ? getIn(errors, path) : undefined;
  };

  const renderRow = ({
    index,
    setField,
    value: macro
  }: RenderRowParams<MacroRow>): ReactElement => {
    const getFieldProps = (key: 'description' | 'name' | 'value') => ({
      dataTestId: `${dataTestId}-${index}-${key}`,
      disabled,
      error: getError(index, key),
      fullWidth: true,
      onBlur: (): void => {
        setFieldTouched(`${fieldName}.${index}.${key}`, true, false);
      },
      onChange: (event: ChangeEvent<HTMLInputElement>): void =>
        setField(key, event.target.value),
      value: macro[key]
    });

    return (
      // Name and value side by side as designed; the description joins them
      // once the list is wide enough.
      <div className="grid grid-cols-2 gap-2 @[700px]:grid-cols-3">
        <TextField {...getFieldProps('name')} label={t(labelName)} required />
        <TextField
          {...getFieldProps('value')}
          // Keeps the browser from filling a saved login in.
          autoComplete={macro.isPassword ? 'new-password' : 'off'}
          label={t(labelValue)}
          placeholder={
            macro.hasStoredPassword && macro.isPassword ? '********' : undefined
          }
          type={macro.isPassword ? 'password' : 'text'}
        />
        <TextField
          {...getFieldProps('description')}
          containerClassName="col-span-2 @[700px]:col-span-1"
          label={t(labelDescription)}
        />
      </div>
    );
  };

  return (
    <div className="flex flex-col gap-2" data-testid={dataTestId}>
      <Typography fontWeight="bold" variant="body1">
        {label}
      </Typography>
      {/* The shared list has no read-only mode: a disabled fieldset turns off
          its add, delete and drag buttons natively. */}
      <fieldset className="m-0 min-w-0 border-0 p-0" disabled={disabled}>
        <SortableEntriesList<MacroRow>
          actions={({ index, value: macro }) => [
            {
              icon: macro.isPassword ? (
                <LockIcon color="primary" />
              ) : (
                <LockOpenIcon />
              ),
              id: `${dataTestId}-password`,
              label: labelPassword,
              onClick: (): void => {
                setFieldValue(
                  `${fieldName}.${index}.isPassword`,
                  !macro.isPassword
                );
              }
            }
          ]}
          addLabel={t(labelAddNewEntry)}
          createValue={createMacroRow}
          draggable={!disabled}
          label={label}
          maxVisibleRows={maxVisibleRows}
          onChange={(nextMacros): void => {
            setFieldValue(fieldName, nextMacros);
          }}
          renderRow={renderRow}
          values={macros}
        />
      </fieldset>
    </div>
  );
};

export default Macros;
