import { useTranslation } from 'react-i18next';

import { InputProps, Group, InputType } from '@centreon/ui';

import {
  labelContactGroups,
  labelContacts,
  labelDescription,
  labelName,
  labelResourceSelection,
  labelRuleProperies,
  labelStatus
} from '../../translatedLabels';
import { findContactGroupsEndpoint } from '../api/endpoints';

interface UseFormInputsState {
  groups: Array<Group>;
  inputs: Array<InputProps>;
}

const useFormInputs = (): UseFormInputsState => {
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
    },
    {
      name: t(labelContactGroups),
      order: 4
    }
  ];

  const inputs: Array<InputProps> = [
    {
      dataTestId: t(labelName),
      fieldName: 'ruleName',
      group: groups[0].name,
      label: t(labelName),
      required: true,
      type: InputType.Text
    },
    {
      dataTestId: t(labelDescription),
      fieldName: 'ruleDescription',
      group: groups[0].name,
      label: t(labelDescription),
      required: true,
      type: InputType.Text
    },
    {
      dataTestId: t(labelStatus),
      fieldName: 'ruleActivated',
      group: groups[0].name,
      label: t(labelStatus),
      type: InputType.Switch
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        endpoint: findContactGroupsEndpoint
      },
      dataTestId: t(labelContactGroups),
      disableSortedOptions: true,
      fieldName: 'contactGroups.ids',
      group: groups[3].name,
      label: t(labelContactGroups),
      required: true,
      type: InputType.MultiConnectedAutocomplete
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
