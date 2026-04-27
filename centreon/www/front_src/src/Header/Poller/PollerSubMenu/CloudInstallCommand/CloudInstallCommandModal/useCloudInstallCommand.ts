import {
  Method,
  getData,
  useMutationQuery,
  useRequest,
  useSnackbar
} from '@centreon/ui';

import { useAtom, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import {
  createPollerEndpoint,
  exportPollerConfigurationEndpoint,
  getPollerRegistrationCommandEndpoint
} from '../../../../api/endpoints';

import {
  generatedCommandAtom,
  isCloudInstallCommandModalOpenAtom,
  pollerIdAtom
} from '../atoms';
import type { CloudInstallCommandFormValues } from '../models';

import {
  labelConfigurationExported,
  labelFailedToCreatePoller,
  labelFailedToExportConfiguration,
  labelPollerCreatedSuccessfully
} from '../../../translatedLabels';

interface UseCloudInstallCommandState {
  submit: (values: CloudInstallCommandFormValues) => Promise<void>;
  close: () => void;
  isOpen: boolean;
}

export const useCloudInstallCommand = (): UseCloudInstallCommandState => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useAtom(isCloudInstallCommandModalOpenAtom);
  const setGeneratedCommand = useSetAtom(generatedCommandAtom);
  const setPollerId = useSetAtom(pollerIdAtom);

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { mutateAsync: createPoller } = useMutationQuery({
    getEndpoint: () => createPollerEndpoint,
    method: Method.POST
  });

  const close = useCallback(() => {
    setIsOpen(false);
    setGeneratedCommand(null);
    setPollerId(null);
  }, []);

  const submit = useCallback(async (values: CloudInstallCommandFormValues) => {
    try {
      const pollerResponse = await createPoller({
        payload: {
          environment: values.environment,
          name: values.pollerName.trim(),
          token: {
            name: values.token?.name
          }
        }
      });

      const pollerId = pollerResponse?.id;

      if (!pollerId) {
        showErrorMessage(t(labelFailedToCreatePoller));

        return;
      }

      const commandResponse = await getData({
        endpoint: getPollerRegistrationCommandEndpoint(pollerId)
      });

      const command = commandResponse?.command;

      setGeneratedCommand(command || '');
      setPollerId(pollerId);

      showSuccessMessage(t(labelPollerCreatedSuccessfully));
    } catch {
      showErrorMessage(t(labelFailedToCreatePoller));
    }
  }, []);

  return {
    close,
    isOpen,
    submit
  };
};

// re-check later
export const useValidatePoller = () => {
  const { t } = useTranslation();
  const setIsOpen = useSetAtom(isCloudInstallCommandModalOpenAtom);
  const [pollerId, setPollerId] = useAtom(pollerIdAtom);
  const setGeneratedCommand = useSetAtom(generatedCommandAtom);

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { sendRequest: sendExportRequest, sending: isExporting } = useRequest({
    defaultFailureMessage: t(labelFailedToExportConfiguration),
    request: getData
  });

  const validate = useCallback(async () => {
    if (!pollerId) return;

    try {
      await sendExportRequest({
        endpoint: exportPollerConfigurationEndpoint(pollerId)
      });
      showSuccessMessage(t(labelConfigurationExported));
      setIsOpen(false);
      setGeneratedCommand(null);
      setPollerId(null);
    } catch {
      showErrorMessage(t(labelFailedToExportConfiguration));
    }
  }, [pollerId]);

  return {
    isExporting,
    validate
  };
};
