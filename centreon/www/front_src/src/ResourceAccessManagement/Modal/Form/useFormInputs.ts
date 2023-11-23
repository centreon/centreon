import { useTranslation } from 'react-i18next';

import { Group, InputProps, InputType } from '@centreon/ui';

import {
  labelContacts,
  labelDescription,
  labelName,
  labelResourceSelection,
  labelRuleProperies,
  labelStatus
} from '../../translatedLabels';

type UseFormInputsType = {
  groups: Array<Group>;
  inputs: Array<InputProps>;
};

const useFormInputs = (): UseFormInputsType => {
  const { t } = useTranslation();

  const groups: Array<Group> = [
    {
      name: t(labelRuleProperies),
      order: 1
    },
    {
      name: t(labelResourceSelection),
      order: 2
    },
    {
      name: t(labelContacts),
      order: 3
    }
  ];

  const inputs: Array<InputProps> = [
    {
      dataTestId: 'resourceAccessRuleName',
      fieldName: t(labelName),
      group: groups[0].name,
      label: t(labelName),
      required: true,
      type: InputType.Text
    },
    {
      dataTestId: 'resourceAccessRuleDescription',
      fieldName: t(labelDescription),
      group: groups[0].name,
      label: t(labelDescription),
      type: InputType.Text
    },
    {
      dataTestId: 'resourceAccessRuleStatus',
      fieldName: t(labelStatus),
      group: groups[0].name,
      label: t(labelStatus),
      type: InputType.Switch
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
