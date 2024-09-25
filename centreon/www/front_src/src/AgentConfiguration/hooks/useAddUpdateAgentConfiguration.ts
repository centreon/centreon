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
import { openFormModalAtom } from '../atoms';
import { AgentConfiguration, AgentConfigurationAPI } from '../models';
import {
  labelAgentConfigurationCreated,
  labelAgentConfigurationUpdated
} from '../translatedLabels';

const adaptAgentConfigurationToAPI = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationAPI => ({
  ...agentConfiguration,
  pollers: pluck('id', agentConfiguration.pollers),
  type: (agentConfiguration.type as SelectEntry).id,
  configuration: {
    otel_private_key: agentConfiguration.configuration.otelPrivateKey,
    otel_ca_certificate: agentConfiguration.configuration.otelCaCertificate,
    otel_public_certificate:
      agentConfiguration.configuration.otelPublicCertificate,
    conf_certificate: agentConfiguration.configuration.confCertificate,
    conf_private_key: agentConfiguration.configuration.confPrivateKey,
    conf_server_port: agentConfiguration.configuration.confServerPort
  }
});

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
      }
    });

    const submit = (
      values: AgentConfiguration,
      { setSubmitting }: FormikHelpers<AgentConfigurationAPI>
    ) => {
      mutateAsync({
        payload: adaptAgentConfigurationToAPI(values),
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
