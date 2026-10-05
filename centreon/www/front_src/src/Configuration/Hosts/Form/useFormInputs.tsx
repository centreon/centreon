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
  isCloudPlatform: boolean;
}

const useFormInputs = ({
  canEdit,
  isCloudPlatform
}: Props): FormInputsState => {
  const { t } = useTranslation();

  const context = { isCloudPlatform, t };
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

  return {
    groups,
    // Frozen here rather than per input: the sections to come add dozens of
    // fields, and one forgotten `getDisabled` is an editable field on a form
    // its user may only read.
    inputs: inputs.map((input) => ({ ...input, getDisabled: () => !canEdit }))
  };
};

export default useFormInputs;
