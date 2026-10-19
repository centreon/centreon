import { type InputPropsWithoutGroup, InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';

import {
  labelIncremental,
  labelReplacement,
  labelUpdateMode
} from '../translatedLabels';

// Legacy's `mc_mod_*` radios: Incremental adds to what each host has,
// Replacement overwrites it, even with an empty list.
export type UpdateMode = 'incremental' | 'replacement';

// Each list legacy offers the choice for, and where it lands in the payload.
// Contacts and contact groups share one choice, as legacy's `mc_mod_hcg`.
export const updateModeFields = {
  categories: [['category_ids']],
  childHosts: [['child_host_ids']],
  contacts: [
    ['notifications', 'contacts'],
    ['notifications', 'contact_groups']
  ],
  groups: [['host_group_ids']],
  notificationOptions: [['notifications', 'options']],
  parentHosts: [['parent_host_ids']],
  templates: [['template_ids']]
} as const;

export type UpdateModeField = keyof typeof updateModeFields;

const updateModeOptions = [
  { label: labelIncremental, value: 'incremental' },
  { label: labelReplacement, value: 'replacement' }
];

export const getUpdateModeInput = (
  field: UpdateModeField,
  t: TFunction
): InputPropsWithoutGroup => ({
  dataTestId: `host-form-update-mode-${field}`,
  fieldName: `massChangeModes.${field}`,
  label: t(labelUpdateMode),
  segmentedButtons: { options: updateModeOptions },
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
        label: `host-form-update-mode-${field}-row`,
        type: InputType.Grid
      }
    : input;
