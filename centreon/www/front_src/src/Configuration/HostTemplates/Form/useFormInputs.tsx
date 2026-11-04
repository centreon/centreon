import type { InputProps } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { type FormInputs, getFormInputs } from '../../ResourceForm/formInputs';
import { placeBlocks } from './layout';
import { getBlocks } from './sections';

interface Props {
  canEdit: boolean;
  isAdditiveInheritanceEnabled: boolean;
  isCloudPlatform: boolean;
}

// Applied to every input, grid columns included: a locked template is
// read-only whatever the user's rights.
const disableWhenLocked = <Input extends Omit<InputProps, 'group'>>(
  input: Input
): Input => ({
  ...input,
  getDisabled: (values) => !!values?.isLocked || !!input.getDisabled?.(values),
  ...(input.grid && {
    grid: { ...input.grid, columns: input.grid.columns.map(disableWhenLocked) }
  })
});

const useFormInputs = ({
  canEdit,
  isAdditiveInheritanceEnabled,
  isCloudPlatform
}: Props): FormInputs => {
  const { t } = useTranslation();

  const context = { isAdditiveInheritanceEnabled, isCloudPlatform, t };

  const { groups, inputs } = getFormInputs({
    canEdit,
    placedSections: placeBlocks({ blocks: getBlocks(context), t }),
    t
  });

  return { groups, inputs: inputs.map(disableWhenLocked) };
};

export default useFormInputs;
