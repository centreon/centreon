import { Box } from '@mui/material';

import { Group, InputProps, InputType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { connectorsEndpoint, JSONLDEntitiesListDecoder } from '../api';
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
import { useUserPermissions } from '../useUserPermissions';
import CommandLine from './CommandLine/CommandLine';
import CommandType from './CommandType/CommandType';
import EnableShellSyntax from './EnableShellSyntax/EnableShellSyntax';
import { useInputsStyles } from './Modal.styles';

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { classes } = useInputsStyles();
  const { t } = useTranslation();

  const { canEdit } = useUserPermissions();

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1'
  };

  return {
    groups: [
      {
        isDividerHidden: true,
        name: t(labelGeneralInformation),
        order: 1,
        titleAttributes
      },
      {
        isDividerHidden: true,
        name: t(labelAdditionalInformation),
        order: 2,
        titleAttributes
      }
    ],
    inputs: [
      {
        fieldName: 'name',
        grid: {
          columns: [
            {
              fieldName: 'name',
              getDisabled: ({ isFromMonitoringConnector }) =>
                !canEdit || isFromMonitoringConnector,
              label: t(labelName),
              required: true,
              type: InputType.Text
            },
            {
              custom: { Component: Box },
              fieldName: '',
              label: '',
              type: InputType.Custom
            }
          ],
          gridTemplateColumns: '1fr 1fr'
        },
        group: t(labelGeneralInformation),
        label: t(labelName),
        required: true,
        type: InputType.Grid
      },
      {
        additionalLabel: `${t(labelCommandType)} *`,
        custom: { Component: CommandType },
        fieldName: 'commandType',
        group: t(labelGeneralInformation),
        label: t(labelCommandType),
        required: true,
        type: InputType.Custom
      },
      {
        additionalLabel: `${t(labelCommandLine)} *`,
        custom: { Component: CommandLine },
        fieldName: 'commandLine',
        group: t(labelGeneralInformation),
        label: t(labelCommandLine),
        required: true,
        type: InputType.Custom
      },
      {
        custom: { Component: EnableShellSyntax },
        fieldName: 'enableShellSyntax',
        group: t(labelGeneralInformation),
        label: t(labelEnableShellSyntax),
        required: true,
        type: InputType.Custom
      },
      {
        grid: {
          columns: [
            {
              connectedAutocomplete: {
                decoder: JSONLDEntitiesListDecoder,
                endpoint: connectorsEndpoint,
                filterKey: 'name',
                getOptionLabel: (option) => option?.name,
                useNewAPIFormat: true
              },
              fieldName: 'connector',
              getDisabled: ({ isFromMonitoringConnector }) =>
                !canEdit || isFromMonitoringConnector,
              label: t(labelSelectOptimizationConnector),
              type: InputType.SingleConnectedAutocomplete
            },
            {
              custom: { Component: Box },
              fieldName: '',
              label: '',
              type: InputType.Custom
            }
          ],
          gridTemplateColumns: '1fr 1fr'
        },
        group: t(labelAdditionalInformation),
        type: InputType.Grid
      },
      {
        fieldName: 'comment',
        getDisabled: ({ isFromMonitoringConnector }) =>
          !canEdit || isFromMonitoringConnector,
        group: t(labelAdditionalInformation),
        label: t(labelComments),
        text: {
          multilineRows: 3
        },
        type: InputType.Text
      }
    ]
  };
};
