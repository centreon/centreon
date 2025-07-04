import { Group, InputProps, InputType } from '@centreon/ui';
import { Box, capitalize } from '@mui/material';
import { useAtom } from 'jotai';
import { equals, isNil, map } from 'ramda';
import { useTranslation } from 'react-i18next';
import { listTokensDecoder } from '../api/decoders';
import {
  listTokensEndpoint,
  pollersEndpoint,
  tokensSearchConditions
} from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
import { AgentType, ConnectionMode } from '../models';
import {
  labelAgent,
  labelAgentType,
  labelCMA,
  labelCMAauthenticationToken,
  labelCaCertificate,
  labelConfigurationServer,
  labelConnectionInitiatedByPoller,
  labelEncryptionLevel,
  labelMonitoredHosts,
  labelName,
  labelNoTLS,
  labelOTLPReceiver,
  labelOTelServer,
  labelParameters,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelSelectExistingCMATokens,
  labelTLS
} from '../translatedLabels';
import HostConfigurations from './HostConfigurations/HostConfigurations';
import { useInputsStyles } from './Modal.styles';
import RedirectToTokensPage from './RedirectToTokensPage';
import EncryptionLevelWarning from './Warning/Warning';

interface SelectEntry {
  id: string;
  name: string;
}

export const agentTypes: Array<SelectEntry> = [
  { id: AgentType.Telegraf, name: capitalize(AgentType.Telegraf) },
  { id: AgentType.CMA, name: labelCMA }
];

export const connectionModes: Array<SelectEntry> = [
  { id: ConnectionMode.secure, name: labelTLS },
  { id: ConnectionMode.noTLS, name: labelNoTLS }
];

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { classes } = useInputsStyles();
  const { t } = useTranslation();

  const [agentTypeForm, setAgentTypeForm] = useAtom(agentTypeFormAtom);

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1'
  };

  const isCMA = equals(agentTypeForm, AgentType.CMA);
  const publicCertificateProperty = 'configuration.otelPublicCertificate';
  const caCertificateProperty = 'configuration.otelCaCertificate';
  const privateKeyProperty = 'configuration.otelPrivateKey';

  return {
    groups: [
      {
        name: t(labelAgent),
        order: 1,
        titleAttributes,
        isDividerHidden: true
      },
      {
        name: t(labelParameters),
        order: 2,
        titleAttributes,
        isDividerHidden: true
      },
      {
        name: t(labelCMAauthenticationToken),
        order: 3,
        titleAttributes,
        isDividerHidden: true
      }
    ],
    inputs: [
      {
        type: InputType.Grid,
        group: t(labelAgent),
        fieldName: 'name_type',
        label: t(labelName),
        grid: {
          gridTemplateColumns: '1fr 1fr 1fr',
          columns: [
            {
              type: InputType.Text,
              fieldName: 'name',
              required: true,
              label: t(labelName)
            },
            {
              type: InputType.SingleAutocomplete,
              fieldName: 'type',
              required: true,
              label: t(labelAgentType),
              autocomplete: {
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
                        isReverse: false,
                        otelPublicCertificate: '',
                        otelCaCertificate: null,
                        otelPrivateKey: '',
                        hosts: []
                      }
                });
                setTouched({}, false);
              }
            },
            {
              type: InputType.SingleAutocomplete,
              fieldName: 'connectionMode',
              required: true,
              label: t(labelEncryptionLevel),
              autocomplete: {
                options: map(
                  ({ id, name }) => ({ id, name: t(name) }),
                  connectionModes
                )
              }
            }
          ]
        }
      },
      {
        group: t(labelAgent),
        type: InputType.Custom,
        fieldName: '',
        label: '',
        hideInput: (values) =>
          isNil(values.type) ||
          isNil(values?.connectionMode) ||
          !equals(values?.connectionMode?.id, ConnectionMode.noTLS),
        custom: {
          Component: EncryptionLevelWarning
        }
      },
      {
        type: InputType.Grid,
        group: t(labelParameters),
        hideInput: (values) => isNil(values.type),
        fieldName: '',
        label: labelParameters,
        grid: {
          gridTemplateColumns: '1fr 2fr',
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
                      customQueryParameters: [
                        { name: 'exclude_central', value: true }
                      ],
                      endpoint: pollersEndpoint,
                      filterKey: 'name',
                      chipColor: 'primary'
                    }
                  },
                  {
                    type: InputType.Switch,
                    fieldName: 'configuration.isReverse',
                    hideInput: (values) =>
                      equals(values?.type?.id, AgentType.Telegraf),
                    label: t(labelConnectionInitiatedByPoller),
                    change: ({ value, values, setValues }) => {
                      setValues({
                        ...values,
                        configuration: {
                          ...values.configuration,
                          isReverse: value,
                          hosts: value
                            ? [
                                {
                                  address: '',
                                  port: '',
                                  pollerCaCertificate: '',
                                  pollerCaName: '',
                                  token: null
                                }
                              ]
                            : []
                        }
                      });
                    }
                  }
                ]
              }
            },
            {
              type: InputType.Grid,
              fieldName: '',
              label: '',
              grid: {
                gridTemplateColumns: '1fr',
                columns: [
                  {
                    type: InputType.Grid,
                    fieldName: '',
                    label: t(isCMA ? labelOTLPReceiver : labelOTelServer),
                    additionalLabel: t(
                      isCMA ? labelOTLPReceiver : labelOTelServer
                    ),
                    hideInput: (values) =>
                      equals(values?.connectionMode?.id, ConnectionMode.noTLS),
                    grid: {
                      columns: [
                        {
                          type: InputType.Text,
                          fieldName: publicCertificateProperty,
                          label: t(labelPublicCertificate)
                        },
                        {
                          type: InputType.Text,
                          fieldName: caCertificateProperty,
                          label: t(labelCaCertificate)
                        },
                        {
                          type: InputType.Text,
                          fieldName: privateKeyProperty,
                          label: t(labelPrivateKey)
                        }
                      ],
                      gridTemplateColumns: 'repeat(2, 1fr)'
                    }
                  },
                  {
                    type: InputType.Grid,
                    fieldName: '',
                    hideInput: (values) =>
                      equals(values?.type?.id, AgentType.CMA),
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
                          hideInput: (values) =>
                            equals(
                              values?.connectionMode?.id,
                              ConnectionMode.noTLS
                            ),
                          type: InputType.Text,
                          fieldName: 'configuration.confCertificate',
                          label: t(labelPublicCertificate)
                        },
                        {
                          hideInput: (values) =>
                            equals(
                              values?.connectionMode?.id,
                              ConnectionMode.noTLS
                            ),
                          type: InputType.Text,
                          fieldName: 'configuration.confPrivateKey',
                          label: t(labelPrivateKey)
                        }
                      ]
                    }
                  },
                  {
                    type: InputType.Custom,
                    fieldName: 'host_configurations',
                    label: labelMonitoredHosts,
                    additionalLabel: t(labelMonitoredHosts),
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
        }
      },
      {
        hideInput: ({ type, connectionMode, configuration }) =>
          !equals(type?.id, AgentType.CMA) ||
          equals(connectionMode?.id, ConnectionMode.noTLS) ||
          configuration?.isReverse,
        fieldName: '',
        label: '',
        group: t(labelCMAauthenticationToken),
        type: InputType.Grid,
        grid: {
          gridTemplateColumns: '2fr 1fr',
          columns: [
            {
              type: InputType.MultiConnectedAutocomplete,
              fieldName: 'configuration.tokens',
              required: true,
              label: t(labelSelectExistingCMATokens),
              connectedAutocomplete: {
                additionalConditionParameters: tokensSearchConditions,
                endpoint: listTokensEndpoint,
                filterKey: 'token_name',
                chipColor: 'primary',
                limitTags: 15,
                decoder: listTokensDecoder
              }
            },
            {
              hideInput: ({ type, connectionMode, configuration }) =>
                !equals(type?.id, AgentType.CMA) ||
                equals(connectionMode?.id, ConnectionMode.noTLS) ||
                configuration?.isReverse,
              fieldName: '',
              label: '',
              type: InputType.Custom,
              custom: {
                Component: Box
              }
            }
          ]
        }
      },
      {
        group: t(labelCMAauthenticationToken),
        fieldName: '',
        label: '',
        type: InputType.Custom,
        custom: {
          Component: RedirectToTokensPage
        },
        hideInput: ({ type, connectionMode, configuration }) =>
          !equals(type?.id, AgentType.CMA) ||
          equals(connectionMode?.id, ConnectionMode.noTLS) ||
          configuration?.isReverse
      }
    ]
  };
};
