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
} from '../../translatedLabels';
import {
  createMacroRow,
  isUnchangedInherited,
  type MacroParent,
  type MacroRow
} from './macroRows';
import type { InheritedMacrosConfig } from './models';
import StoredSecretField from './StoredSecretField';
import useInheritedMacros from './useInheritedMacros';

export type MacrosProps = InputPropsWithoutGroup & {
  inheritance: InheritedMacrosConfig;
};

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

// A resource's custom macros, `$_HOST<NAME>$` on a host, in order: its own,
// then the inherited ones. The rows' test ids derive from `dataTestId`.
const Macros = ({
  dataTestId,
  fieldName,
  getDisabled,
  inheritance,
  label
}: MacrosProps): ReactElement => {
  const { t } = useTranslation();
  const {
    errors,
    setFieldTouched,
    setFieldValue,
    submitCount,
    touched,
    values
  } = useFormikContext<FormikValues>();

  useInheritedMacros({ config: inheritance, fieldName });

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
    setValue,
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
        <StoredSecretField
          {...getFieldProps('value')}
          // Keeps the browser from filling a saved login in.
          autoComplete={macro.isPassword ? 'new-password' : 'off'}
          isErased={macro.isErased}
          isStored={macro.hasStoredPassword}
          label={t(labelValue)}
          onErase={(): void =>
            setValue({ ...macro, isErased: true, value: '' })
          }
          onRevert={(): void =>
            setValue({ ...macro, isErased: false, value: '' })
          }
          type={macro.isPassword ? 'password' : 'text'}
        />
      </div>
    );
  };

  return (
    <div className="flex flex-col gap-2" data-testid={dataTestId}>
      <Typography className="text-base font-bold">{label}</Typography>
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
              // As legacy: a stored password stays one.
              isDisabled: macro.hasStoredPassword,
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
              macro.parent && isUnchangedInherited(macro)
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
