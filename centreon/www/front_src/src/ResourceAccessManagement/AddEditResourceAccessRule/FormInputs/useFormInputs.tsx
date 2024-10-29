import { useTranslation } from 'react-i18next';

import { Divider, Typography } from '@mui/material';
import { Variant } from '@mui/material/styles/createTypography';

import { Group, InputProps, InputType } from '@centreon/ui';

import {
  labelAddResourceDatasets,
  labelContactGroups,
  labelContacts,
  labelContactsAndContactGroups,
  labelDescription,
  labelName,
  labelRuleProperies,
  labelStatus
} from '../../translatedLabels';

import { useFormInputStyles } from './FormInputs.styles';
import ActivateSwitch from './components/ActivateSwitch';
import ContactGroupsSelector from './components/ContactGroupsSelector';
import ContactsSelector from './components/ContactsSelector';
import ResourceSelection from './components/ResourceSelection';

interface UseFormInputsState {
  groups: Array<Group>;
  inputs: Array<InputProps>;
}

const useFormInputs = (): UseFormInputsState => {
  const { t } = useTranslation();
  const { classes } = useFormInputStyles();

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
      name: t(labelAddResourceDatasets),
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
                  custom: {
                    Component: () => <ActivateSwitch />
                  },
                  dataTestId: t(labelStatus),
                  fieldName: 'isActivated',
                  label: t(labelStatus),
                  type: InputType.Custom
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
                  dataTestId: t(labelAddResourceDatasets),
                  fieldName: 'resourceSelection',
                  grid: {
                    alignItems: 'left',
                    className: classes.resourceSelection,
                    columns: [
                      {
                        custom: {
                          Component: () => <ResourceSelection />
                        },
                        dataTestId: t(labelAddResourceDatasets),
                        fieldName: 'datasetFilters',
                        label: t(labelAddResourceDatasets),
                        type: InputType.Custom
                      }
                    ]
                  },
                  label: t(labelAddResourceDatasets),
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
                            <div>
                              <Divider
                                className={
                                  classes.contactsAndContactGroupsDivider
                                }
                              />
                              <Typography className={classes.titleGroup}>
                                {t(labelContactsAndContactGroups)}
                              </Typography>
                            </div>
                          )
                        },
                        fieldName: 'contactsAndContactGroupsTitle',
                        label: 'contactsAndContactGroupsTitle',
                        type: InputType.Custom
                      },
                      {
                        custom: {
                          Component: () => <ContactsSelector />
                        },
                        dataTestId: t(labelContacts),
                        disableSortedOptions: true,
                        fieldName: 'contacts',
                        label: t(labelContacts),
                        required: true,
                        type: InputType.Custom
                      },
                      {
                        custom: {
                          Component: () => <ContactGroupsSelector />
                        },
                        dataTestId: t(labelContactGroups),
                        disableSortedOptions: true,
                        fieldName: 'contactGroups',
                        label: t(labelContactGroups),
                        required: true,
                        type: InputType.Custom
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
        gridTemplateColumns: '1fr 2fr'
      },
      group: '',
      label: '',
      type: InputType.Grid
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
