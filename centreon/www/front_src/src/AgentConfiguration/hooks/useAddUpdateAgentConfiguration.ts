import {
  Method,
  SelectEntry,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { FormikHelpers } from 'formik';
import { useAtom } from 'jotai';
import { equals, map, omit, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint
} from '../api/endpoints';
import { agentTypeFormAtom, openFormModalAtom } from '../atoms';
import {
  AgentConfiguration,
  AgentConfigurationAPI,
  AgentType,
  CMAConfiguration,
  ConnectionMode,
  TelegrafConfiguration
} from '../models';
import {
  labelAgentConfigurationCreated,
  labelAgentConfigurationUpdated
} from '../translatedLabels';

const adaptTelegrafConfigurationToAPI = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationAPI => {
  const configuration =
    agentConfiguration.configuration as TelegrafConfiguration;

  const getFieldBasedOnCertificate = (field) =>
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.secure) ||
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.insecure)
      ? field
      : null;

  return {
    ...omit(['pollers', 'connectionMode'], agentConfiguration),
    configuration: {
      conf_certificate: getFieldBasedOnCertificate(
        configuration.confCertificate
      ),
      conf_private_key: getFieldBasedOnCertificate(
        configuration.confPrivateKey
      ),
      conf_server_port: configuration.confServerPort,
      otel_ca_certificate: getFieldBasedOnCertificate(
        configuration.otelCaCertificate
      ),
      otel_private_key: getFieldBasedOnCertificate(
        configuration.otelPrivateKey
      ),
      otel_public_certificate: getFieldBasedOnCertificate(
        configuration.otelPublicCertificate
      )
    },
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    type: (agentConfiguration.type as SelectEntry).id
  };
};

const adaptCMAConfigurationToAPI = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationAPI => {
  const configuration = agentConfiguration.configuration as CMAConfiguration;

  const getFieldBasedOnCertificate = (field) =>
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.secure) ||
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.insecure)
      ? field
      : null;

  return {
    ...omit(['pollers', 'connectionMode'], agentConfiguration),
    configuration: {
      agent_initiated: configuration.agentInitiated,
      hosts: configuration.hosts.map((host) => ({
        address: host.address,
        id: host.id,
        poller_ca_certificate: getFieldBasedOnCertificate(
          host.pollerCaCertificate
        ),
        poller_ca_name: getFieldBasedOnCertificate(host.pollerCaName),
        port: host.port,
        token: configuration.pollerInitiated
          ? {
              creator_id: host?.token?.creatorId,
              name: host?.token?.name
            }
          : null
      })),
      otel_ca_certificate: getFieldBasedOnCertificate(
        configuration.otelCaCertificate
      ),
      otel_private_key: getFieldBasedOnCertificate(
        configuration.otelPrivateKey
      ),
      otel_public_certificate: getFieldBasedOnCertificate(
        configuration.otelPublicCertificate
      ),
      poller_initiated: configuration.pollerInitiated,
      port: configuration.agentInitiated ? configuration?.port : null,
      tokens: configuration.agentInitiated
        ? map(
            ({ name, creatorId }) => ({ creator_id: creatorId, name }),
            agentConfiguration.configuration.tokens
          )
        : []
    },
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    type: (agentConfiguration.type as SelectEntry).id
  };
};

interface UseAddUpdateAgentConfigurationState {
  submit: (
    values: AgentConfiguration,
    { setSubmitting }: FormikHelpers<AgentConfigurationAPI>
  ) => void;
}

export const useAddUpdateAgentConfiguration =
  (): UseAddUpdateAgentConfigurationState => {
    const { t } = useTranslation();

    const { showSuccessMessage } = useSnackbar();
    const queryClient = useQueryClient();

    const [openFormModal, setOpenFormModal] = useAtom(openFormModalAtom);
    const [agentTypeForm, setAgentTypeForm] = useAtom(agentTypeFormAtom);

    const { mutateAsync } = useMutationQuery<
      AgentConfigurationAPI,
      { id; setSubmitting }
    >({
      getEndpoint: ({ id }) =>
        id ? getAgentConfigurationEndpoint(id) : getAgentConfigurationsEndpoint,
      method: equals(openFormModal, 'add') ? Method.POST : Method.PUT,
      onMutate: ({ _meta }) => {
        _meta.setSubmitting(true);
      },
      onSettled: (_data, _error, { _meta }) => {
        _meta.setSubmitting(false);
      },
      onSuccess: (_data, { _meta }) => {
        showSuccessMessage(
          t(
            _meta.id
              ? labelAgentConfigurationUpdated
              : labelAgentConfigurationCreated
          )
        );
        queryClient.invalidateQueries({
          queryKey: ['listAgentConfigurations']
        });
        setOpenFormModal(null);
        setAgentTypeForm(null);
      }
    });

    const submit = (
      values: AgentConfiguration,
      { setSubmitting }: FormikHelpers<AgentConfigurationAPI>
    ) => {
      mutateAsync({
        _meta: {
          id: equals(openFormModal, 'add') ? null : openFormModal,
          setSubmitting
        },
        payload: equals(agentTypeForm, AgentType.Telegraf)
          ? adaptTelegrafConfigurationToAPI(values)
          : adaptCMAConfigurationToAPI(values)
      });
    };

    return {
      submit
    };
  };
