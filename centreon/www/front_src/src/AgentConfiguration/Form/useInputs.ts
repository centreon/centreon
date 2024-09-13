import { Group, InputProps, InputType } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { pollersEndpoint } from '../api/endpoints';
import { AgentType } from '../models';
import {
  labelAgentConfiguration,
  labelAgentType,
  labelListeningAddress,
  labelName,
  labelOTelServer,
  labelPollerConfiguration,
  labelPollers,
  labelPort
} from '../translatedLabels';
import { portRegex } from './useValidationSchema';
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
        label: t(labelName)
      },
      {
        type: InputType.SingleAutocomplete,
        group: t(labelAgentConfiguration),
        fieldName: 'type',
        required: true,
        label: t(labelAgentType),
        autocomplete: {
          options: [
            { id: AgentType.Telegraf, name: capitalize(AgentType.Telegraf) }
          ]
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
              additionalLabel: t(labelOTelServer),
              grid: {
                columns: [
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.otelServerAddress',
                    required: true,
                    label: t(labelListeningAddress),
                    change: ({ setFieldValue, setFieldTouched, value }) => {
                      const port = value.match(portRegex);

                      if (isNil(port) || isEmpty(port)) {
                        setFieldValue('configuration.otelServerAddress', value);
                        return;
                      }

                      const newAddress = value.replace(port[0], '');

                      setFieldTouched('configuration.otelServerPort', true);
                      setFieldValue(
                        'configuration.otelServerAddress',
                        newAddress
                      );
                      setFieldValue(
                        'configuration.otelServerPort',
                        port[0].substring(1)
                      );
                    }
                  },
                  {
                    type: InputType.Text,
                    fieldName: 'configuration.otelServerPort',
                    required: true,
                    label: t(labelPort)
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
