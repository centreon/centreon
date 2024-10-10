import { Group, InputProps, InputType, SelectEntry } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useAtom } from 'jotai';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { pollersEndpoint } from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
import { AgentType } from '../models';
import {
  labelAgentConfiguration,
  labelAgentType,
  labelCMA,
  labelCaCertificate,
  labelCertificate,
  labelConfigurationServer,
  labelConnectionInitiatedByPoller,
  labelHostConfigurations,
  labelName,
  labelOTLPReceiver,
  labelOTelServer,
  labelParameters,
  labelPollerCaCertificateFileName,
  labelPollerCaName,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate
} from '../translatedLabels';
import Empty from './Empty';
import HostConfigurations from './HostConfigurations/HostConfigurations';

export const agentTypes: Array<SelectEntry> = [
  { id: AgentType.Telegraf, name: capitalize(AgentType.Telegraf) },
  { id: AgentType.CMA, name: labelCMA }
];

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { t } = useTranslation();
  const [agentTypeForm, setAgentTypeForm] = useAtom(agentTypeFormAtom);

  const isCMA = equals(agentTypeForm, AgentType.CMA);
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
        name: t(labelParameters),
        order: 2
      }
    ],
    inputs: [
      {
        type: InputType.Grid,
        group: t(labelAgentConfiguration),
        fieldName: 'name_type',
        label: t(labelName),
        grid: {
          gridTemplateColumns: '0.5fr 0.5fr 1fr',
          columns: [
            {
              type: InputType.SingleAutocomplete,
              fieldName: 'type',
              required: true,
              label: t(labelAgentType),
              autocomplete: {
                fullWidth: false,
                options: agentTypes
              },
              change: ({ value, setValues, values, setTouched }) => {
                setAgentTypeForm(value.id);
                setValues({
                  ...values,
                  type: value,
                  configuration: equals(value.id, AgentType.Telegraf)
                    ? {
                        confServerPort: 1443,
                        otelPrivateKey: '',
                        otelCaCertificate: null,
                        otelPublicCertificate: '',
                        confPrivateKey: '',
                        confCertificate: ''
                      }
                    : {
                        isReverse: true,
                        otlpCertificate: '',
                        otlpCaCertificate: null,
                        otlpCaCertificateName: null,
                        otlpPrivateKey: '',
                        hosts: [
                          {
                            address: '',
                            port: '',
                            certificate: '',
                            key: ''
                          }
                        ]
                      }
                });
                setTouched({}, false);
              }
            },
            {
              type: InputType.Text,
              fieldName: 'name',
              required: true,
              label: t(labelName),
              text: {
                fullWidth: false
              }
            }
          ]
        }
      },
      {
        type: InputType.Grid,
        group: t(labelParameters),
        hideInput: (values) => isNil(values.type),
        fieldName: '',
        label: '',
        grid: {
          gridTemplateColumns: '0.6fr 1fr',
          columns: [
            {
              type: InputType.Grid,
              fieldName: 'poller_reverse',
              label: '',
              additionalLabel: t(labelPollers),
              grid: {
                gridTemplateColumns: '1fr',
                columns: [
                  {
                    type: InputType.MultiConnectedAutocomplete,
                    fieldName: 'pollers',
                    required: true,
                    label: t(labelPollers),
                    connectedAutocomplete: {
                      additionalConditionParameters: [],
                      endpoint: pollersEndpoint,
                      filterKey: 'name'
                    }
                  },
                  {
                    type: InputType.Switch,
                    fieldName: 'configuration.isReverse',
                    hideInput: (values) =>
                      equals(values?.type?.id, AgentType.Telegraf),
                    label: t(labelConnectionInitiatedByPoller)
                  }
                ]
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
                  },
                  {
                    type: InputType.Custom,
                    fieldName: 'empty',
                    label: '',
                    custom: {
                      Component: Empty
                    }
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.pollerCaCertificate',
                    hideInput: (values) =>
                      equals(values?.type?.id, AgentType.Telegraf) ||
                      !values?.configuration?.isReverse,
                    required: false,
                    label: t(labelPollerCaCertificateFileName)
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.pollerCaName',
                    hideInput: (values) =>
                      equals(values?.type?.id, AgentType.Telegraf) ||
                      !values?.configuration?.isReverse,
                    required: false,
                    label: t(labelPollerCaName)
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
              additionalLabel: t(labelHostConfigurations),
              hideInput: (values) =>
                equals(values?.type?.id, AgentType.Telegraf) ||
                !values?.configuration?.isReverse,
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
