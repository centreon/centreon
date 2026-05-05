import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';
import { useQueryClient } from '@tanstack/react-query';
import { FormikHelpers } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { equals, map, omit, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint
} from '../api/endpoints';
import { agentTypeFormAtom, isEditingAtom, openFormModalAtom } from '../atoms';
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
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    configuration: {
      otel_private_key: getFieldBasedOnCertificate(
        configuration.otelPrivateKey
      ),
      otel_ca_certificate: getFieldBasedOnCertificate(
        configuration.otelCaCertificate
      ),
      otel_public_certificate: getFieldBasedOnCertificate(
        configuration.otelPublicCertificate
      ),
      conf_certificate: getFieldBasedOnCertificate(
        configuration.confCertificate
      ),
      conf_private_key: getFieldBasedOnCertificate(
        configuration.confPrivateKey
      ),
      conf_server_port: configuration.confServerPort
    }
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
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    configuration: {
      port: configuration.agentInitiated ? configuration?.port : null,
      agent_initiated: configuration.agentInitiated,
      create_host_auto: configuration.agentInitiated
        ? Boolean(configuration?.createHostAuto)
        : false,
      poller_initiated: configuration.pollerInitiated,
      tokens: configuration.agentInitiated
        ? map(
            ({ name, creatorId }) => ({ name, creator_id: creatorId }),
            agentConfiguration.configuration.tokens
          )
        : [],
      otel_ca_certificate: getFieldBasedOnCertificate(
        configuration.otelCaCertificate
      ),
      otel_public_certificate: getFieldBasedOnCertificate(
        configuration.otelPublicCertificate
      ),
      otel_private_key: getFieldBasedOnCertificate(
        configuration.otelPrivateKey
      ),
      hosts: configuration.hosts.map((host) => ({
        id: host.id,
        address: host.address,
        port: host.port,
        poller_ca_name: getFieldBasedOnCertificate(host.pollerCaName),
        poller_ca_certificate: getFieldBasedOnCertificate(
          host.pollerCaCertificate
        ),
        token: configuration.pollerInitiated
          ? {
              name: host?.token?.name,
              creator_id: host?.token?.creatorId
            }
          : null
      }))
    }
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
    const isEditing = useAtomValue(isEditingAtom);
    const [agentTypeForm, setAgentTypeForm] = useAtom(agentTypeFormAtom);

    const { mutateAsync } = useMutationQuery<
      AgentConfigurationAPI,
      { id; setSubmitting }
    >({
      getEndpoint: ({ id }) =>
        id ? getAgentConfigurationEndpoint(id) : getAgentConfigurationsEndpoint,
      method: isEditing ? Method.PUT : Method.POST,
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
        queryClient.invalidateQueries({ queryKey: ['agent-configurations'] });
        setOpenFormModal(null);
        setAgentTypeForm(null);
      }
    });

    const submit = (
      values: AgentConfiguration,
      { setSubmitting }: FormikHelpers<AgentConfigurationAPI>
    ) => {
      const agentConfiguration = isEditing
        ? omit(['type'], values)
        : { ...values, type: values.type.id };

      const payload = (
        equals(agentTypeForm, AgentType.Telegraf)
          ? adaptTelegrafConfigurationToAPI
          : adaptCMAConfigurationToAPI
      )(agentConfiguration);

      return mutateAsync({
        payload,
        _meta: {
          setSubmitting,
          id: isEditing ? openFormModal : null
        }
      });
    };

    return {
      submit
    };
  };
