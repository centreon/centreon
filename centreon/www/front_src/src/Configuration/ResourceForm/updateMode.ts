import { type InputPropsWithoutGroup, InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';

import {
  labelIncremental,
  labelReplacement,
  labelUpdateMode
} from './translatedLabels';

// Legacy's `mc_mod_*` radios: Incremental adds to what each resource has,
// Replacement overwrites it, even with an empty list. Another mode is one more
// entry here and in `updateModeLabels`, its payload key in
// `updateModeSuffixes`.
export type UpdateMode = 'incremental' | 'replacement';

// The payload key each mode sends a list under: `x_to_add` or `x`.
export const updateModeSuffixes: Record<UpdateMode, string> = {
  incremental: '_to_add',
  replacement: ''
};

const updateModeLabels: Record<UpdateMode, string> = {
  incremental: labelIncremental,
  replacement: labelReplacement
};

const defaultModes: Array<UpdateMode> = ['incremental', 'replacement'];

interface UpdateModeInputProps {
  field: string;
  idPrefix: string;
  // The modes the field offers, in order.
  modes?: Array<UpdateMode>;
  t: TFunction;
}

export const getUpdateModeInput = ({
  field,
  idPrefix,
  modes = defaultModes,
  t
}: UpdateModeInputProps): InputPropsWithoutGroup => ({
  dataTestId: `${idPrefix}-update-mode-${field}`,
  fieldName: `massChangeModes.${field}`,
  label: t(labelUpdateMode),
  segmentedButtons: {
    options: modes.map((value) => ({ label: updateModeLabels[value], value }))
  },
  type: InputType.SegmentedButtons
});

// Puts a list input above its update mode, in mass change only.
export const withUpdateMode = <Input extends InputPropsWithoutGroup>({
  input,
  isMassChange,
  ...updateMode
}: UpdateModeInputProps & {
  input: Input;
  isMassChange?: boolean;
}): Input | InputPropsWithoutGroup =>
  isMassChange
    ? {
        fieldName: `${updateMode.field}-update-mode-row`,
        grid: {
          className: 'grid-cols-1',
          columns: [input, getUpdateModeInput(updateMode)]
        },
        label: `${updateMode.idPrefix}-update-mode-${updateMode.field}-row`,
        type: InputType.Grid
      }
    : input;
