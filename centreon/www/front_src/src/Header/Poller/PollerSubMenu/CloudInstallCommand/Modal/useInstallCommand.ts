import { platformFeaturesAtom } from '@centreon/ui-context';

import {
  centreonBaseURL,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { createPollerEndpoint } from '../../../../api/endpoints';
import { labelFailedToCreatePoller } from '../../../translatedLabels';
import { generatedCommandAtom, isModalOpenAtom, pollerIdAtom } from '../atoms';
import type { CloudInstallCommandFormValues } from '../models';

interface PollerResponse {
  id: number;
  installation_command: string;
}

interface UseInstallCommandState {
  submit: (values: CloudInstallCommandFormValues) => Promise<void>;
  close: () => void;
  isOpen: boolean;
}

export const useInstallCommand = (): UseInstallCommandState => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useAtom(isModalOpenAtom);
  const setGeneratedCommand = useSetAtom(generatedCommandAtom);
  const setPollerId = useSetAtom(pollerIdAtom);
  const  platformFeatures = useAtomValue(platformFeaturesAtom)

  const { showErrorMessage } = useSnackbar();

  const { mutateAsync: createPoller } = useMutationQuery({
    getEndpoint: () => createPollerEndpoint,
    method: Method.POST
  });

  const close = useCallback(() => {
    setIsOpen(false);
    setGeneratedCommand(null);
  }, []);

  const submit = useCallback(async (values: CloudInstallCommandFormValues) => {
    try {

      const central_address = platformFeatures?.isCloudPlatform ? "I don't know" : values?.centralAddress

      const pollerResponse = await createPoller({
        payload: {
          address: values.pollerAddress.trim(),
          name: values.pollerName.trim(),
          poller_type: values.environment,
          poller_token_name: values?.token?.name,
          central_address
        }
      });

      const response = pollerResponse as PollerResponse;
      const pollerId = response?.id;

      if (!pollerId) {
        showErrorMessage(t(labelFailedToCreatePoller));

        return;
      }

      setPollerId(pollerId);

      const centralUrl = `${window.location.origin}${centreonBaseURL}`;
      const command = (response?.installation_command || '')
        .split('<CENTRAL_URL>')
        .join(centralUrl);

      setGeneratedCommand(command);
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
