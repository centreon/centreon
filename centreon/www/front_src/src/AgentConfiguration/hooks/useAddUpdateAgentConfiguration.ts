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
  AgentConfigurationAPI,
  AgentConfigurationForm,
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
  agentConfiguration: AgentConfigurationForm
): AgentConfigurationAPI => {
  const configuration =
    agentConfiguration.configuration as TelegrafConfiguration;

  const getFieldBasedOnCertificate = (field: string | null): string | null =>
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.secure) ||
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.insecure)
      ? field
      : null;

  return {
    ...omit(['pollers', 'connectionMode', 'type'], agentConfiguration),
    ...(agentConfiguration.type
      ? { type: agentConfiguration.type.id as AgentType }
      : {}),
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
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>
  };
};

const adaptCMAConfigurationToAPI = (
  agentConfiguration: AgentConfigurationForm
): AgentConfigurationAPI => {
  const configuration = agentConfiguration.configuration as CMAConfiguration;

  const getFieldBasedOnCertificate = (field: string | null): string | null =>
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.secure) ||
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.insecure)
      ? field
      : null;

  return {
    ...omit(['pollers', 'connectionMode', 'type'], agentConfiguration),
    ...(agentConfiguration.type
      ? { type: agentConfiguration.type.id as AgentType }
      : {}),
    configuration: {
      agent_initiated: configuration.agentInitiated,
      create_host_auto: configuration.agentInitiated
        ? Boolean(configuration?.createHostAuto)
        : false,
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
            ({ name, creatorId }: { name: string; creatorId: number }) => ({
              creator_id: creatorId,
              name
            }),
            configuration.tokens || []
          )
        : []
    },
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>
  };
};

interface UseAddUpdateAgentConfigurationState {
  submit: (
    values: AgentConfigurationForm,
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
      { id: number | null; setSubmitting: (isSubmitting: boolean) => void }
    >({
      getEndpoint: ({ id }) =>
        id ? getAgentConfigurationEndpoint(id) : getAgentConfigurationsEndpoint,
      method: isEditing ? Method.PUT : Method.POST,
      onMutate: ({ _meta }) => {
        _meta?.setSubmitting(true);
      },
      onSettled: (_data, _error, variables) => {
        (
          variables as unknown as {
            _meta?: { setSubmitting: (v: boolean) => void };
          }
        )?._meta?.setSubmitting(false);
      },
      onSuccess: (_data, { _meta }) => {
        showSuccessMessage(
          t(
            _meta?.id
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
      values: AgentConfigurationForm,
      { setSubmitting }: FormikHelpers<AgentConfigurationAPI>
    ) => {
      const agentConfiguration: AgentConfigurationForm = isEditing
        ? { ...values, type: null }
        : values;

      const payload = (
        equals(agentTypeForm, AgentType.Telegraf)
          ? adaptTelegrafConfigurationToAPI
          : adaptCMAConfigurationToAPI
      )(agentConfiguration);

      return mutateAsync({
        _meta: {
          id: isEditing ? (openFormModal as number) : null,
          setSubmitting
        },
        payload
      });
    };

    return {
      submit
    };
  };
