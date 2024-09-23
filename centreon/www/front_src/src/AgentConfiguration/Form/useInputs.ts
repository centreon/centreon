import { Group, InputProps, InputType, SelectEntry } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useAtom } from 'jotai';
import { equals, isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { pollersEndpoint } from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
import { AgentType } from '../models';
import {
  labelAgentConfiguration,
  labelAgentType,
  labelCaCertificate,
  labelCertificate,
  labelConfigurationServer,
  labelHostConfigurations,
  labelListeningAddress,
  labelName,
  labelOTelServer,
  labelOTLPReceiver,
  labelPollerConfiguration,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate
} from '../translatedLabels';
import Empty from './Empty';
import HostConfigurations from './HostConfigurations/HostConfigurations';
import { portRegex } from './useValidationSchema';

export const agentTypes: Array<SelectEntry> = [
  { id: AgentType.Telegraf, name: capitalize(AgentType.Telegraf) },
  { id: AgentType.CMA, name: 'CMA' }
];

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { t } = useTranslation();
  const [agentTypeForm, setAgentTypeForm] = useAtom(agentTypeFormAtom);

  const isCMA = equals(agentTypeForm, AgentType.CMA);
  const listeningAddressProperty = `configuration.${isCMA ? 'otlpReceiverAddress' : 'otelServerAddress'}`;
  const listeningPortProperty = `configuration.${isCMA ? 'otlpReceiverPort' : 'otelServerProps'}`;
  const publicCertificateProperty = `configuration.${isCMA ? 'otlpCertificate' : 'otelPublicCertificate'}`;
  const caCertificateProperty = `configuration.${isCMA ? 'otlpCaCertificate' : 'otelCaCertificate'}`;
  const privateKeyProperty = `configuration.${isCMA ? 'otlpPrivateKey' : 'otelPrivateKey'}`;

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
        },
        change: ({ setFieldValue, value }) => {
          setAgentTypeForm(value.id);
          setFieldValue('type', value);
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
                endpoint: pollersEndpoint,
                filterKey: 'name'
              }
            },
            {
              type: InputType.Grid,
              fieldName: '',
              label: '',
              additionalLabel: t(isCMA ? labelOTLPReceiver : labelOTelServer),
              grid: {
                columns: [
                  {
                    type: InputType.Text,
                    fieldName: listeningAddressProperty,
                    required: true,
                    label: t(labelListeningAddress),
                    change: ({ setFieldValue, setFieldTouched, value }) => {
                      const port = value.match(portRegex);

                      if (isNil(port) || isEmpty(port)) {
                        setFieldValue(listeningAddressProperty, value);
                        return;
                      }

                      const newAddress = value.replace(port[0], '');

                      setFieldTouched(listeningPortProperty, true);
                      setFieldValue(listeningAddressProperty, newAddress);
                      setFieldValue(
                        listeningPortProperty,
                        port[0].substring(1)
                      );
                    }
                  },
                  {
                    type: InputType.Text,
                    fieldName: listeningPortProperty,
                    required: true,
                    label: t(labelPort),
                    text: {
                      type: 'number'
                    }
                  },
                  {
                    type: InputType.Text,
                    fieldName: publicCertificateProperty,
                    required: true,
                    label: t(labelPublicCertificate)
                  },
                  {
                    type: InputType.Text,
                    fieldName: caCertificateProperty,
                    required: true,
                    label: t(labelCaCertificate)
                  },
                  {
                    type: InputType.Text,
                    fieldName: privateKeyProperty,
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
              hideInput: (values) => equals(values?.type?.id, AgentType.CMA),
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
            },
            {
              type: InputType.Custom,
              fieldName: 'host_configurations',
              label: labelHostConfigurations,
              hideInput: (values) =>
                equals(values?.type?.id, AgentType.Telegraf),
              custom: {
                Component: HostConfigurations
              }
            }
          ]
        }
      }
    ]
  };
};
