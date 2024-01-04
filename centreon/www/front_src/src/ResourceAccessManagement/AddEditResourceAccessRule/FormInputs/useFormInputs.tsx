import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';
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
      fieldName: '',
      grid: {
        alignItems: 'center',
        columns: [
          {
            fieldName: 'ruleProperties',
            grid: {
              alignItems: 'left',
              className: classes.ruleProperties,
              columns: [
                {
                  custom: {
                    Component: () => (
                      <Typography className={classes.titleGroup}>
                        {t(labelRuleProperies)}
                      </Typography>
                    )
                  },
                  fieldName: 'rulePropertiesTitle',
                  label: 'rulePropertiesTitle',
                  type: InputType.Custom
                },
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
            label: t(labelRuleProperies),
            type: InputType.Grid
          },
          {
            fieldName: '',
            grid: {
              alignItems: 'center',
              className: classes.resourceSelection,
              columns: [
                {
                  dataTestId: t(labelResourceSelection),
                  fieldName: 'resourceSelection',
                  grid: {
                    alignItems: 'left',
                    className: classes.resourceSelection,
                    columns: [
                      {
                        custom: {
                          Component: () => (
                            <ResourceDataset propertyName="datasetFilters" />
                          )
                        },
                        dataTestId: t(labelResourceSelection),
                        fieldName: 'datasetFilters',
                        label: t(labelResourceSelection),
                        type: InputType.Custom
                      }
                    ]
                  },
                  label: t(labelResourceSelection),
                  type: InputType.Grid
                },
                {
                  dataTestId: t(labelContactsAndContactGroups),
                  fieldName: 'contactsAndContactGroups',
                  grid: {
                    alignItems: 'left',
                    className: classes.contactsAndContactGroups,
                    columns: [
                      {
                        custom: {
                          Component: () => (
                            <Typography className={classes.titleGroup}>
                              {t(labelContactsAndContactGroups)}
                            </Typography>
                          )
                        },
                        fieldName: 'contactsAndContactGroupsTitle',
                        label: 'contactsAndContactGroupsTitle',
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
                        label: t(labelContactGroups),
                        required: true,
                        type: InputType.MultiConnectedAutocomplete
                      }
                    ]
                  },
                  label: t(labelContactsAndContactGroups),
                  type: InputType.Grid
                }
              ]
            },
            label: '',
            type: InputType.Grid
          }
        ],
        gridTemplateColumns: '4fr 5fr'
      },
      group: '',
      label: '',
      type: InputType.Grid
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
