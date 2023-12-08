import { useTranslation } from 'react-i18next';

import { Variant } from '@mui/material/styles/createTypography';

import { InputProps, Group, InputType } from '@centreon/ui';

import {
  labelContactGroups,
  labelContacts,
  labelContactsAndContactGroups,
  labelDescription,
  labelName,
  labelResourceSelection,
  labelRuleProperies,
  labelStatus
} from '../../translatedLabels';
import {
  findContactGroupsEndpoint,
  findContactsEndpoint
} from '../api/endpoints';

import { useInputStyles } from './Inputs.styles';
import ResourceDataset from './ResourceDataset';

interface UseFormInputsState {
  groups: Array<Group>;
  inputs: Array<InputProps>;
}

const useFormInputs = (): UseFormInputsState => {
  const { t } = useTranslation();
  const { classes } = useInputStyles();

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1' as Variant
  };

  const groups: Array<Group> = [
    {
      name: t(labelRuleProperies),
      order: 1,
      titleAttributes
    },
    {
      name: t(labelResourceSelection),
      order: 2,
      titleAttributes
    },
    {
      name: t(labelContactsAndContactGroups),
      order: 3,
      titleAttributes
    }
  ];

  const inputs: Array<InputProps> = [
    {
      dataTestId: t(labelRuleProperies),
      fieldName: 'ruleProperties',
      grid: {
        alignItems: 'left',
        className: classes.ruleProperties,
        columns: [
          {
            dataTestId: t(labelName),
            fieldName: 'name',
            label: t(labelName),
            required: true,
            type: InputType.Text
          },
          {
            dataTestId: t(labelDescription),
            fieldName: 'description',
            label: t(labelDescription),
            text: {
              multilineRows: 4
            },
            type: InputType.Text
          },
          {
            dataTestId: t(labelStatus),
            fieldName: 'isActivated',
            label: t(labelStatus),
            type: InputType.Switch
          }
        ]
      },
      group: groups[0].name,
      label: t(labelRuleProperies),
      type: InputType.Grid
    },
    {
      custom: {
        Component: () => <ResourceDataset propertyName="service" />
      },
      dataTestId: t(labelResourceSelection),
      fieldName: 'datasetFilters',
      group: groups[1].name,
      label: t(labelResourceSelection),
      type: InputType.Custom
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        endpoint: findContactsEndpoint
      },
      dataTestId: t(labelContacts),
      disableSortedOptions: true,
      fieldName: 'contacts',
      group: groups[2].name,
      label: t(labelContacts),
      required: true,
      type: InputType.MultiConnectedAutocomplete
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        endpoint: findContactGroupsEndpoint
      },
      dataTestId: t(labelContactGroups),
      disableSortedOptions: true,
      fieldName: 'contactGroups',
      group: groups[2].name,
      label: t(labelContactGroups),
      required: true,
      type: InputType.MultiConnectedAutocomplete
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
