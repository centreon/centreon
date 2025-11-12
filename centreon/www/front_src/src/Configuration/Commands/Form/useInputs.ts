import { Group, InputProps, InputType } from '@centreon/ui';
import { Box } from '@mui/material';

import { useTranslation } from 'react-i18next';
import { useInputsStyles } from './Modal.styles';

import CommandLine from './CommandLine/CommandLine';
import CommandType from './CommandType/CommandType';
import EnableShellSyntax from './EnableShellSyntax/EnableShellSyntax';

import { JSONLDEntitiesListDecoder, connectorsEndpoint } from '../api';

import {
  labelAdditionalInformation,
  labelCommandLine,
  labelCommandType,
  labelComments,
  labelEnableShellSyntax,
  labelGeneralInformation,
  labelName,
  labelSelectOptimizationConnector
} from '../translatedLabels';

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { classes } = useInputsStyles();
  const { t } = useTranslation();

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1'
  };

  return {
    groups: [
      {
        name: t(labelGeneralInformation),
        order: 1,
        titleAttributes,
        isDividerHidden: true
      },
      {
        name: t(labelAdditionalInformation),
        order: 2,
        titleAttributes,
        isDividerHidden: true
      }
    ],
    inputs: [
      {
        type: InputType.Grid,
        group: t(labelGeneralInformation),
        required: true,
        fieldName: 'name',
        label: t(labelName),
        grid: {
          gridTemplateColumns: '1fr 1fr',
          columns: [
            {
              fieldName: 'name',
              required: true,
              label: t(labelName),
              type: InputType.Text
            },
            {
              type: InputType.Custom,
              custom: { Component: Box },
              fieldName: '',
              label: ''
            }
          ]
        }
      },
      {
        type: InputType.Custom,
        custom: { Component: CommandType },
        fieldName: 'commandType',
        required: true,
        label: t(labelCommandType),
        additionalLabel: t(labelCommandType),
        group: t(labelGeneralInformation)
      },
      {
        type: InputType.Custom,
        custom: { Component: CommandLine },
        fieldName: 'commandLine',
        required: true,
        label: t(labelCommandLine),
        additionalLabel: t(labelCommandLine),
        group: t(labelGeneralInformation)
      },
      {
        type: InputType.Custom,
        custom: { Component: EnableShellSyntax },
        fieldName: 'enableShellSyntax',
        required: true,
        label: t(labelEnableShellSyntax),
        group: t(labelGeneralInformation)
      },
      {
        type: InputType.Grid,
        group: t(labelAdditionalInformation),
        grid: {
          gridTemplateColumns: '1fr 1fr',
          columns: [
            {
              connectedAutocomplete: {
                endpoint: connectorsEndpoint,
                filterKey: 'name',
                decoder: JSONLDEntitiesListDecoder
              },
              fieldName: 'connector',
              label: t(labelSelectOptimizationConnector),
              type: InputType.SingleConnectedAutocomplete
            },
            {
              type: InputType.Custom,
              custom: { Component: Box },
              fieldName: '',
              label: ''
            }
          ]
        }
      },
      {
        type: InputType.Text,
        fieldName: 'comment',
        label: t(labelComments),
        text: {
          multilineRows: 3
        },
        group: t(labelAdditionalInformation)
      }
    ]
  };
};
