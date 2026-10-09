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
  labelCommandInheritance,
  labelName,
  labelPassword,
  labelTemplateInheritance,
  labelValue
} from '../translatedLabels';

export type MacroParent = 'command' | 'template';

export interface MacroRow {
  // A password the server keeps but never sends back: left empty, it is kept.
  hasStoredPassword: boolean;
  // Null for a new macro, and for a command macro the command never recorded.
  id: number | null;
  isPassword: boolean;
  name: string;
  // Where an inherited macro comes from, null for the host's own.
  parent: MacroParent | null;
  value: string;
}

const maxVisibleRows = 10;

// Legacy's colours for inherited macros, the same in both of its themes.
const parentClassName: Record<MacroParent, string> = {
  command: 'bg-[#b6fca7] dark:bg-[#b6fca7]/30',
  template: 'bg-[#ffcb6b] dark:bg-[#ffcb6b]/30'
};

const legend: Array<{ label: string; parent: MacroParent }> = [
  { label: labelTemplateInheritance, parent: 'template' },
  { label: labelCommandInheritance, parent: 'command' }
];

const createMacroRow = (): MacroRow => ({
  hasStoredPassword: false,
  id: null,
  isPassword: false,
  name: '',
  parent: null,
  value: ''
});

// Still what the host inherits: once changed, it is the host's own override,
// as legacy shows it.
const isUnchangedInherited = (
  macro: MacroRow,
  initialMacros: Array<MacroRow>
): boolean =>
  !!macro.parent &&
  initialMacros.some(
    (initialMacro) =>
      initialMacro.parent === macro.parent &&
      initialMacro.id === macro.id &&
      initialMacro.name === macro.name &&
      initialMacro.value === macro.value &&
      initialMacro.isPassword === macro.isPassword
  );

// The host's macros, `$_HOST<NAME>$`, in order: its own, then the inherited ones.
const Macros = ({
  dataTestId,
  fieldName,
  getDisabled,
  label
}: InputPropsWithoutGroup): ReactElement => {
  const { t } = useTranslation();
  const {
    errors,
    initialValues,
    setFieldTouched,
    setFieldValue,
    submitCount,
    touched,
    values
  } = useFormikContext<FormikValues>();

  const macros = (getIn(values, fieldName) ?? []) as Array<MacroRow>;
  const initialMacros = (getIn(initialValues, fieldName) ??
    []) as Array<MacroRow>;
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
    const getFieldProps = (key: 'name' | 'value') => ({
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
      <div className="grid grid-cols-2 gap-2">
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
      </div>
    );
  };

  return (
    <div className="flex flex-col gap-2" data-testid={dataTestId}>
      <Typography className="font-bold" variant="body1">
        {label}
      </Typography>
      <div className="flex flex-wrap gap-x-4 gap-y-1">
        {legend.map(({ label: legendLabel, parent }) => (
          <Typography
            className="flex items-center gap-1"
            key={parent}
            variant="body2"
          >
            <span
              aria-hidden
              className={`inline-block size-3 rounded-sm ${parentClassName[parent]}`}
            />
            {t(legendLabel)}
          </Typography>
        ))}
      </div>
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
          getRowClassName={({ value: macro }) =>
            `rounded p-1 ${
              macro.parent && isUnchangedInherited(macro, initialMacros)
                ? parentClassName[macro.parent]
                : ''
            }`
          }
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
