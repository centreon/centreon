import {
  Method,
  SelectEntry,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { useQueryClient } from '@tanstack/react-query';
import { FormikHelpers } from 'formik';
import { useAtom } from 'jotai';
import { equals, pluck } from 'ramda';
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

  return {
    ...agentConfiguration,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    type: (agentConfiguration.type as SelectEntry).id,
    configuration: {
      otel_private_key: configuration.otelPrivateKey,
      otel_ca_certificate: configuration.otelCaCertificate,
      otel_public_certificate: configuration.otelPublicCertificate,
      conf_certificate: configuration.confCertificate,
      conf_private_key: configuration.confPrivateKey,
      conf_server_port: configuration.confServerPort
    }
  };
};

const adaptCMAConfigurationToAPI = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationAPI => {
  const configuration = agentConfiguration.configuration as CMAConfiguration;

  return {
    ...agentConfiguration,
    pollers: pluck('id', agentConfiguration.pollers),
    type: (agentConfiguration.type as SelectEntry).id,
    configuration: {
      is_reverse: configuration.isReverse,
      otel_ca_certificate: configuration.otelCaCertificate,
      otel_public_certificate: configuration.otelPublicCertificate,
      otel_private_key: configuration.otelPrivateKey,
      hosts: configuration.hosts.map((host) => ({
        address: host.address,
        port: host.port,
        poller_ca_name: host.pollerCaName,
        poller_ca_certificate: host.pollerCaCertificate
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
        queryClient.invalidateQueries({ queryKey: ['agent-configurations'] });
        setOpenFormModal(null);
        setAgentTypeForm(null);
      }
    });

    const submit = (
      values: AgentConfiguration,
      { setSubmitting }: FormikHelpers<AgentConfigurationAPI>
    ) => {
      mutateAsync({
        payload: equals(agentTypeForm, AgentType.Telegraf)
          ? adaptTelegrafConfigurationToAPI(values)
          : adaptCMAConfigurationToAPI(values),
        _meta: {
          setSubmitting,
          id: equals(openFormModal, 'add') ? null : openFormModal
        }
      });
    };

    return {
      submit
    };
  };
