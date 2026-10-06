import type { Group, InputProps } from '@centreon/ui';

import { chain } from 'ramda';
import { useTranslation } from 'react-i18next';

import { getAvailableSections } from './sections';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

interface Props {
  canEdit: boolean;
  isAdditiveInheritanceEnabled: boolean;
  isCloudPlatform: boolean;
}

const useFormInputs = ({
  canEdit,
  isAdditiveInheritanceEnabled,
  isCloudPlatform
}: Props): FormInputsState => {
  const { t } = useTranslation();

  const context = { isAdditiveInheritanceEnabled, isCloudPlatform, t };
  const availableSections = getAvailableSections(context);

  const groups: Array<Group> = availableSections.map(({ order, section }) => ({
    name: t(section.label),
    order
  }));

  const inputs: Array<InputProps> = chain(
    ({ section }) =>
      section
        .getInputs(context)
        .map((input) => ({ ...input, group: t(section.label) })),
    availableSections
  );

  // Once for every input, grid columns included, so none is left editable.
  const disableWithoutWriteAccess = <Input extends Omit<InputProps, 'group'>>(
    input: Input
  ): Input => ({
    ...input,
    getDisabled: () => !canEdit,
    ...(input.grid && {
      grid: {
        ...input.grid,
        columns: input.grid.columns.map(disableWithoutWriteAccess)
      }
    })
  });

  return {
    groups,
    inputs: inputs.map(disableWithoutWriteAccess)
  };
};

export default useFormInputs;
