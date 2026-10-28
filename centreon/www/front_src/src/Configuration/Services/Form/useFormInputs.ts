import type { Group, InputProps } from '@centreon/ui';

import { chain } from 'ramda';
import { useTranslation } from 'react-i18next';

import { disableWithoutWriteAccess } from './disableWithoutWriteAccess';
import { getAvailableSections, type PlatformContext } from './sections';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

interface Props extends PlatformContext {
  canEdit: boolean;
}

const useFormInputs = ({
  canEdit,
  isAdditiveInheritanceEnabled,
  isCloudPlatform,
  isMassChange = false
}: Props): FormInputsState => {
  const { t } = useTranslation();

  const context = {
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isMassChange,
    t
  };
  const availableSections = getAvailableSections(context);

  // Sections are told apart by their headers, as in the Hosts form: no
  // divider.
  const groups: Array<Group> = availableSections.map(({ order, section }) => ({
    isDividerHidden: true,
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

  return {
    groups,
    inputs: inputs.map(disableWithoutWriteAccess(canEdit))
  };
};

export default useFormInputs;
