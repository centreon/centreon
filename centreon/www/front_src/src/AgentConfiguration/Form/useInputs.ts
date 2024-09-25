import { Group, InputProps, InputType, SelectEntry } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { agentConfigurationPollersEndpoint } from '../api/endpoints';
import { AgentType } from '../models';
import {
  labelAgentConfiguration,
  labelAgentType,
  labelCaCertificate,
  labelCertificate,
  labelConfigurationServer,
  labelName,
  labelOTelServer,
  labelPollerConfiguration,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate
} from '../translatedLabels';
import Empty from './Empty';

export const agentTypes: Array<SelectEntry> = [
  { id: AgentType.Telegraf, name: capitalize(AgentType.Telegraf) }
];

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { t } = useTranslation();

  return {
    groups: [
      {
        name: t(labelAgentConfiguration),
        order: 1
      },
      {
        name: t(labelPollerConfiguration),
        order: 2
      }
    ],
    inputs: [
      {
        type: InputType.Text,
        group: t(labelAgentConfiguration),
        fieldName: 'name',
        required: true,
        label: t(labelName),
        text: {
          fullWidth: false
        }
      },
      {
        type: InputType.SingleAutocomplete,
        group: t(labelAgentConfiguration),
        fieldName: 'type',
        required: true,
        label: t(labelAgentType),
        autocomplete: {
          fullWidth: false,
          options: agentTypes
        }
      },
      {
        type: InputType.Grid,
        group: t(labelPollerConfiguration),
        fieldName: '',
        label: '',
        grid: {
          gridTemplateColumns: '0.6fr 1fr',
          columns: [
            {
              type: InputType.MultiConnectedAutocomplete,
              fieldName: 'pollers',
              required: true,
              label: t(labelPollers),
              additionalLabel: t(labelPollers),
              connectedAutocomplete: {
                additionalConditionParameters: [],
                endpoint: agentConfigurationPollersEndpoint,
                filterKey: 'name'
              }
            },
            {
              type: InputType.Grid,
              fieldName: '',
              label: '',
              additionalLabel: t(labelOTelServer),
              grid: {
                columns: [
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.otelPublicCertificate',
                    required: true,
                    label: t(labelPublicCertificate)
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.otelCaCertificate',
                    label: t(labelCaCertificate)
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.otelPrivateKey',
                    required: true,
                    label: t(labelPrivateKey)
                  }
                ],
                gridTemplateColumns: 'repeat(2, 1fr)'
              }
            },
            {
              type: InputType.Custom,
              fieldName: '',
              label: '',
              custom: {
                Component: Empty
              }
            },
            {
              type: InputType.Grid,
              fieldName: '',
              label: '',
              additionalLabel: t(labelConfigurationServer),
              grid: {
                gridTemplateColumns: 'repeat(2, 1fr)',
                columns: [
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.confServerPort',
                    required: true,
                    label: t(labelPort),
                    text: {
                      type: 'number'
                    }
                  },
                  {
                    type: InputType.Custom,
                    fieldName: '',
                    label: '',
                    custom: {
                      Component: Empty
                    }
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.confCertificate',
                    required: true,
                    label: t(labelCertificate)
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.confPrivateKey',
                    required: true,
                    label: t(labelPrivateKey)
                  }
                ]
              }
            }
          ]
        }
      }
    ]
  };
};
