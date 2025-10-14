import { Group, InputProps, InputType } from '@centreon/ui';
import { Box, capitalize } from '@mui/material';
import { useAtom } from 'jotai';
import { equals, isNil, map } from 'ramda';
import { useTranslation } from 'react-i18next';
import { agentTypeFormAtom } from '../atoms';
import { AgentType, ConnectionMode } from '../models';
import {
  labelAgent,
  labelAgentType,
  labelCMA,
  labelCaCertificate,
  labelConfigurationServer,
  labelConnectionInitiated,
  labelEncryptionLevel,
  labelInsecure,
  labelName,
  labelNoTLS,
  labelOTelServer,
  labelParameters,
  labelPollers,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelTLS
} from '../translatedLabels';
import { useInputsStyles } from './Modal.styles';
import EncryptionLevelWarning from './Warning/Warning';

import { pollersEndpoint } from '../api/endpoints';
import ConnectionInitiated from './ConnectionInitiated/ConnectionInitiated';

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
  {
    id: ConnectionMode.insecure,
    name: labelInsecure
  },
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
                        agentInitiated: true,
                        pollerInitiated: false,
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
        fieldName: '',
        label: '',
        group: t(labelParameters),
        type: InputType.Grid,
        hideInput: (values) => isNil(values.type),
        grid: {
          gridTemplateColumns: '2fr 1fr',
          columns: [
            {
              additionalLabel: t(labelPollers),
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
        group: t(labelParameters),
        hideInput: (values) => isNil(values.type),
        fieldName: '',
        label: labelParameters,
        type: InputType.Grid,
        grid: {
          gridTemplateColumns: '1fr',
          columns: [
            {
              type: InputType.Grid,
              fieldName: '',
              label: t(labelOTelServer),
              additionalLabel: t(labelOTelServer),
              hideInput: (values) =>
                equals(values?.connectionMode?.id, ConnectionMode.noTLS) ||
                isCMA,
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
                    hideInput: (values) =>
                      equals(values?.connectionMode?.id, ConnectionMode.noTLS),
                    type: InputType.Text,
                    fieldName: 'configuration.confCertificate',
                    label: t(labelPublicCertificate)
                  },
                  {
                    hideInput: (values) =>
                      equals(values?.connectionMode?.id, ConnectionMode.noTLS),
                    type: InputType.Text,
                    fieldName: 'configuration.confPrivateKey',
                    label: t(labelPrivateKey)
                  }
                ]
              }
            }
          ]
        }
      },
      {
        fieldName: '',
        label: '',
        group: t(labelParameters),
        type: InputType.Grid,
        hideInput: (values) => !equals(values?.type?.id, AgentType.CMA),
        grid: {
          gridTemplateColumns: '1fr',
          columns: [
            {
              additionalLabel: t(labelConnectionInitiated),
              fieldName: '',
              label: '',
              type: InputType.Custom,
              custom: {
                Component: ConnectionInitiated
              }
            }
          ]
        }
      }
    ]
  };
};
