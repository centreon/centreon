import type { Group, InputProps } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { chain } from 'ramda';

import type { PlacedSection } from './layout';

export interface FormInputs {
  groups: Array<Group>;
  inputs: Array<InputProps>;
}

// Applied to every input, grid columns included, so none is left editable
// without write access. The input's own rule, such as a field that waits for
// another one, is kept.
export const disableWithoutWriteAccess =
  (canEdit: boolean) =>
  <Input extends Omit<InputProps, 'group'>>(input: Input): Input => ({
    ...input,
    getDisabled: (values) => !canEdit || !!input.getDisabled?.(values),
    ...(input.grid && {
      grid: {
        ...input.grid,
        columns: input.grid.columns.map(disableWithoutWriteAccess(canEdit))
      }
    })
  });

// The groups and inputs of the form from its placed sections.
export const getFormInputs = ({
  canEdit,
  placedSections,
  t
}: {
  canEdit: boolean;
  placedSections: Array<PlacedSection>;
  t: TFunction;
}): FormInputs => {
  // Sections are told apart by their headers, as designed: no divider.
  const groups: Array<Group> = placedSections.map(({ label, order }) => ({
    isDividerHidden: true,
    name: t(label),
    order
  }));

  const inputs: Array<InputProps> = chain(
    ({ inputs: sectionInputs, label }) =>
      sectionInputs.map((input) => ({ ...input, group: t(label) })),
    placedSections
  );

  return {
    groups,
    inputs: inputs.map(disableWithoutWriteAccess(canEdit))
  };
};
