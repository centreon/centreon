import { Group, InputProps, InputType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { pollersEndpoint } from '../api/endpoints';
import {
  labelDescription,
  labelGeneralInformation,
  labelName,
  labelParameters,
  labelPort,
  labelSelectPollers,
  labelSelectType,
  labelSettings,
  labelvCenterESX
} from '../translatedLabels';
import ConnectorType from './ConnectorType/ConnectorType';
import { useFormStyles } from './Form.styles';
import Parameters from './Parameters/Parameters';
import Port from './Parameters/Port';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

const useFormInputs = (): FormInputsState => {
  const { t } = useTranslation();
  const { classes } = useFormStyles();

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1'
  };

  const groups = [
    {
      isDividerHidden: true,
      name: t(labelGeneralInformation),
      order: 1,
      titleAttributes
    },
    { name: t(labelSettings), order: 2, titleAttributes }
  ];

  const inputs = [
    {
      grid: {
        columns: [
          {
            dataTestId: labelName,
            fieldName: 'name',
            group: t(labelGeneralInformation),
            label: t(labelName),
            required: true,
            type: InputType.Text
          },
          {
            fieldName: 'description',
            group: t(labelGeneralInformation),
            label: t(labelDescription),
            type: InputType.Text
          }
        ]
      },
      group: t(labelGeneralInformation),
      type: InputType.Grid
    },

    {
      grid: {
        columns: [
          {
            connectedAutocomplete: {
              additionalConditionParameters: [],
              chipColor: 'primary',
              endpoint: pollersEndpoint
            },

            fieldName: 'pollers',
            group: t(labelSettings),
            label: t(labelSelectPollers),
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            custom: {
              Component: ConnectorType
            },
            fieldName: 'type',
            group: t(labelSettings),
            label: t(labelSelectType),
            required: true,
            type: InputType.Custom
          },
          {
            custom: {
              Component: Port
            },
            fieldName: 'prameters.port',
            group: t(labelSettings),
            label: t(labelPort),
            type: InputType.Custom
          }
        ],
        gridTemplateColumns: '3fr 2fr 2fr'
      },
      group: t(labelSettings),
      type: InputType.Grid
    },

    {
      grid: {
        columns: [
          {
            additionalLabel: t(labelvCenterESX),
            custom: {
              Component: Parameters
            },
            fieldName: 'parameters.vcenters',
            group: t(labelSettings),
            label: t(labelParameters),
            type: InputType.Custom
          }
        ]
      },
      group: t(labelSettings),
      type: InputType.Grid
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
