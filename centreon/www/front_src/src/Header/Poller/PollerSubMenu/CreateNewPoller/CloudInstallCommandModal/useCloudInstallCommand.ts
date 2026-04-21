import {
  Method,
  getData,
  useMutationQuery,
  useRequest,
  useSnackbar
} from '@centreon/ui';

import { useAtom } from 'jotai';
import { isNotNil } from 'ramda';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  createPollerEndpoint,
  exportPollerConfigurationEndpoint,
  getPollerRegistrationCommandEndpoint
} from '../../../../api/endpoints';

import { isCloudInstallCommandModalOpenAtom } from '../../atoms';
import { PollerEnvironment } from '../../models';

import {
  labelConfigurationExported,
  labelFailedToCreatePoller,
  labelFailedToExportConfiguration,
  labelPollerCreatedSuccessfully
} from '../../../translatedLabels';

interface CloudInstallCommandState {
  pollerName: string;
  environment: PollerEnvironment | null;
  token: { id: string; name: string } | null;
  generatedCommand: string | null;
  isGenerated: boolean;
  pollerId: number | null;
}

const initialState: CloudInstallCommandState = {
  environment: null,
  generatedCommand: null,
  isGenerated: false,
  pollerId: null,
  pollerName: '',
  token: null
};

export const useCloudInstallCommand = () => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useAtom(isCloudInstallCommandModalOpenAtom);
  const [state, setState] = useState<CloudInstallCommandState>(initialState);
  const [isGenerating, setIsGenerating] = useState(false);

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { sendRequest: sendExportRequest, sending: isExporting } = useRequest({
    defaultFailureMessage: t(labelFailedToExportConfiguration),
    request: getData
  });

  const { mutateAsync: createPoller } = useMutationQuery({
    getEndpoint: () => createPollerEndpoint,
    method: Method.POST
  });

  const close = useCallback(() => {
    setIsOpen(false);
    setState(initialState);
  }, []);

  const open = useCallback(() => {
    setIsOpen(true);
  }, []);

  const setPollerName = useCallback((name: string) => {
    setState((prev) => ({ ...prev, pollerName: name }));
  }, []);

  const setEnvironment = useCallback((env: PollerEnvironment) => {
    setState((prev) => ({ ...prev, environment: env }));
  }, []);

  const setToken = useCallback((_, value) => {
    setState((prev) => ({
      ...prev,
      token: value ? { id: value.id, name: value.name } : null
    }));
  }, []);

  const canGenerate = useMemo(
    () =>
      state.pollerName.trim().length > 0 &&
      isNotNil(state.environment) &&
      isNotNil(state.token),
    [state.pollerName, state.environment, state.token]
  );

  const generate = useCallback(async () => {
    if (!canGenerate) return;

    setIsGenerating(true);

    try {
      const pollerResponse = await createPoller({
        payload: {
          environment: state.environment,
          name: state.pollerName.trim(),
          token: {
            name: state.token?.name
          }
        }
      });

      const pollerId = pollerResponse?.id;

      if (!pollerId) {
        showErrorMessage(t(labelFailedToCreatePoller));
        setIsGenerating(false);

        return;
      }

      const commandResponse = await getData({
        endpoint: getPollerRegistrationCommandEndpoint(pollerId)
      });

      const command = commandResponse?.command;

      setState((prev) => ({
        ...prev,
        generatedCommand: command || '',
        isGenerated: true,
        pollerId
      }));

      showSuccessMessage(t(labelPollerCreatedSuccessfully));
    } catch {
      showErrorMessage(t(labelFailedToCreatePoller));
    } finally {
      setIsGenerating(false);
    }
  }, [canGenerate, state.pollerName, state.environment, state.token]);

  const validate = useCallback(async () => {
    if (!state.pollerId) return;

    try {
      await sendExportRequest({
        endpoint: exportPollerConfigurationEndpoint(state.pollerId)
      });
      showSuccessMessage(t(labelConfigurationExported));
      close();
    } catch {
      showErrorMessage(t(labelFailedToExportConfiguration));
    }
  }, [state.pollerId]);

  return {
    canGenerate,
    close,
    generate,
    isExporting,
    isGenerating,
    isOpen,
    open,
    setEnvironment,
    setPollerName,
    setToken,
    state,
    validate
  };
};
