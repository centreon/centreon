import { type InputPropsWithoutGroup, InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';

import {
  labelIncremental,
  labelRemoval,
  labelReplacement,
  labelUpdateMode
} from '../translatedLabels';

// Legacy's `mc_mod_*` radios, plus Removal (MON-211851, D12): Incremental adds
// to what each service has, Replacement overwrites it, even with an empty
// list, and Removal takes the picked entries out of it.
export type UpdateMode = 'incremental' | 'replacement' | 'removal';

// The payload key each mode sends a list under: `x_to_add`, `x`, `x_to_remove`,
// as the Hosts mass change names them.
export const updateModeSuffixes: Record<UpdateMode, string> = {
  incremental: '_to_add',
  removal: '_to_remove',
  replacement: ''
};

// Each list the mass change offers the choice for, and where it lands in the
// payload. Contacts and contact groups share one choice, as on Hosts.
export const updateModeFields = {
  categories: [['category_ids']],
  contacts: [
    ['notifications', 'contacts'],
    ['notifications', 'contact_groups']
  ],
  notificationOptions: [['notifications', 'options']],
  serviceGroups: [['service_group_ids']],
  traps: [['trap_ids']]
} as const;

export type UpdateModeField = keyof typeof updateModeFields;

// The notification options are flags, not relations: as on Hosts, they are
// added or replaced, never removed.
const fieldModes: Record<UpdateModeField, Array<UpdateMode>> = {
  categories: ['incremental', 'replacement', 'removal'],
  contacts: ['incremental', 'replacement', 'removal'],
  notificationOptions: ['incremental', 'replacement'],
  serviceGroups: ['incremental', 'replacement', 'removal'],
  traps: ['incremental', 'replacement', 'removal']
};

const modeLabels: Record<UpdateMode, string> = {
  incremental: labelIncremental,
  removal: labelRemoval,
  replacement: labelReplacement
};

export const getUpdateModeInput = (
  field: UpdateModeField,
  t: TFunction
): InputPropsWithoutGroup => ({
  dataTestId: `service-form-update-mode-${field}`,
  fieldName: `massChangeModes.${field}`,
  label: t(labelUpdateMode),
  segmentedButtons: {
    options: fieldModes[field].map((value) => ({
      label: modeLabels[value],
      value
    }))
  },
  type: InputType.SegmentedButtons
});

// Puts a list input above its update mode, in mass change only.
export const withUpdateMode = <Input extends InputPropsWithoutGroup>({
  field,
  input,
  isMassChange,
  t
}: {
  field: UpdateModeField;
  input: Input;
  isMassChange?: boolean;
  t: TFunction;
}): Input | InputPropsWithoutGroup =>
  isMassChange
    ? {
        fieldName: `${field}-update-mode-row`,
        grid: {
          className: 'grid-cols-1',
          columns: [input, getUpdateModeInput(field, t)]
        },
        label: `service-form-update-mode-${field}-row`,
        type: InputType.Grid
      }
    : input;
