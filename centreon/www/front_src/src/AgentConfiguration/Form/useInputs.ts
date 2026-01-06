import { Box, capitalize } from '@mui/material';

import { Group, InputProps, InputType } from '@centreon/ui';

import { useAtom } from 'jotai';
import { equals, isNil, map } from 'ramda';
import { useTranslation } from 'react-i18next';

import { pollersEndpoint } from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
import { AgentType, ConnectionMode } from '../models';
import {
  labelAgent,
  labelAgentType,
  labelCaCertificate,
  labelCMA,
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
import ConnectionInitiated from './ConnectionInitiated/ConnectionInitiated';
import { useInputsStyles } from './Modal.styles';
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
        isDividerHidden: true,
        name: t(labelAgent),
        order: 1,
        titleAttributes
      },
      {
        isDividerHidden: true,
        name: t(labelParameters),
        order: 2,
        titleAttributes
      }
    ],
    inputs: [
      {
        fieldName: 'name_type',
        grid: {
          columns: [
            {
              fieldName: 'name',
              label: t(labelName),
              required: true,
              type: InputType.Text
            },
            {
              autocomplete: {
                options: agentTypes
              },
              change: ({ value, setValues, values, setTouched }) => {
                setAgentTypeForm(value.id);
                setValues({
                  ...values,
                  configuration: equals(value.id, AgentType.Telegraf)
                    ? {
                        confCertificate: '',
                        confPrivateKey: '',
                        confServerPort: 1443,
                        otelCaCertificate: null,
                        otelPrivateKey: '',
                        otelPublicCertificate: ''
                      }
                    : {
                        agentInitiated: true,
                        hosts: [],
                        otelCaCertificate: null,
                        otelPrivateKey: '',
                        port: 4317
                      }
                });
                setTouched({}, false);
              },
              fieldName: 'type',
              label: t(labelAgentType),
              required: true,
              type: InputType.SingleAutocomplete
            },
            {
              autocomplete: {
                options: map(
                  ({ id, name }) => ({ id, name: t(name) }),
                  connectionModes
                )
              },
              fieldName: 'connectionMode',
              label: t(labelEncryptionLevel),
              required: true,
              type: InputType.SingleAutocomplete
            }
          ],
          gridTemplateColumns: '1fr 1fr 1fr'
        },
        group: t(labelAgent),
        label: t(labelName),
        type: InputType.Grid
      },
      {
        custom: {
          Component: EncryptionLevelWarning
        },
        fieldName: '',
        group: t(labelAgent),
        hideInput: (values) =>
          isNil(values.type) ||
          isNil(values?.connectionMode) ||
          !equals(values?.connectionMode?.id, ConnectionMode.noTLS),
        label: '',
        type: InputType.Custom
      },
      {
        fieldName: '',
        grid: {
          columns: [
            {
              additionalLabel: t(labelPollers),
              connectedAutocomplete: {
                additionalConditionParameters: [],
                chipColor: 'primary',
                customQueryParameters: [
                  { name: 'exclude_central', value: true }
                ],
                endpoint: pollersEndpoint,
                filterKey: 'name'
              },
              fieldName: 'pollers',
              label: t(labelPollers),
              required: true,
              type: InputType.MultiConnectedAutocomplete
            },
            {
              custom: {
                Component: Box
              },
              fieldName: '',
              label: '',
              type: InputType.Custom
            }
          ],
          gridTemplateColumns: '2fr 1fr'
        },
        group: t(labelParameters),
        hideInput: (values) => isNil(values.type),
        label: '',
        type: InputType.Grid
      },
      {
        fieldName: '',
        grid: {
          columns: [
            {
              additionalLabel: t(labelOTelServer),
              fieldName: '',
              grid: {
                columns: [
                  {
                    fieldName: publicCertificateProperty,
                    label: t(labelPublicCertificate),
                    type: InputType.Text
                  },
                  {
                    fieldName: caCertificateProperty,
                    label: t(labelCaCertificate),
                    type: InputType.Text
                  },
                  {
                    fieldName: privateKeyProperty,
                    label: t(labelPrivateKey),
                    type: InputType.Text
                  }
                ],
                gridTemplateColumns: 'repeat(2, 1fr)'
              },
              hideInput: (values) =>
                equals(values?.connectionMode?.id, ConnectionMode.noTLS) ||
                isCMA,
              label: t(labelOTelServer),
              type: InputType.Grid
            },
            {
              additionalLabel: t(labelConfigurationServer),
              fieldName: '',
              grid: {
                columns: [
                  {
                    fieldName: 'configuration.confServerPort',
                    label: t(labelPort),
                    required: true,
                    text: {
                      type: 'number'
                    },
                    type: InputType.Text
                  },
                  {
                    fieldName: 'configuration.confCertificate',
                    hideInput: (values) =>
                      equals(values?.connectionMode?.id, ConnectionMode.noTLS),
                    label: t(labelPublicCertificate),
                    type: InputType.Text
                  },
                  {
                    fieldName: 'configuration.confPrivateKey',
                    hideInput: (values) =>
                      equals(values?.connectionMode?.id, ConnectionMode.noTLS),
                    label: t(labelPrivateKey),
                    type: InputType.Text
                  }
                ],
                gridTemplateColumns: 'repeat(2, 1fr)'
              },
              hideInput: (values) => equals(values?.type?.id, AgentType.CMA),
              label: '',
              type: InputType.Grid
            }
          ],
          gridTemplateColumns: '1fr'
        },
        group: t(labelParameters),
        hideInput: (values) => isNil(values.type),
        label: labelParameters,
        type: InputType.Grid
      },
      {
        fieldName: '',
        grid: {
          columns: [
            {
              additionalLabel: t(labelConnectionInitiated),
              custom: {
                Component: ConnectionInitiated
              },
              fieldName: '',
              label: '',
              type: InputType.Custom
            }
          ],
          gridTemplateColumns: '1fr'
        },
        group: t(labelParameters),
        hideInput: (values) => !equals(values?.type?.id, AgentType.CMA),
        label: '',
        type: InputType.Grid
      }
    ]
  };
};
