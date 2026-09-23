import {
  centreonBaseURL,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { platformFeaturesAtom } from '@centreon/ui-context';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { createPollerEndpoint } from '../../../../api/endpoints';
import { labelFailedToCreatePoller } from '../../../translatedLabels';
import { generatedCommandAtom, isModalOpenAtom, pollerIdAtom } from '../atoms';
import type { CloudInstallCommandFormValues } from '../models';

// On cloud, the whole application (including /poller/install.sh) is served
// under the platform base path, and behind proxies/NAT only the browser knows
// the client-reachable address — so the central address is host + <base href>,
// never derived server-side.
export const centralWebAddress = {
  get: (): string => `${window.location.host}${centreonBaseURL}`
};

const normalizeAddress = (address?: string): string | undefined => {
  const trimmed = address?.trim();

  if (!trimmed?.includes('://')) {
    return trimmed?.replace(/\/+$/, '');
  }

  try {
    const url = new URL(trimmed);

    return `${url.host}${url.pathname}`.replace(/\/+$/, '');
  } catch {
    return trimmed;
  }
};

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
  const platformFeatures = useAtomValue(platformFeaturesAtom);

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
      const centralAddress = normalizeAddress(
        platformFeatures?.isCloudPlatform
          ? centralWebAddress.get()
          : values?.centralAddress
      );

      const pollerResponse = await createPoller({
        payload: {
          address: values.pollerAddress.trim(),
          central_address: centralAddress,
          name: values.pollerName.trim(),
          poller_token_name: values?.token?.name,
          poller_type: values.environment
        }
      });

      const response = pollerResponse as PollerResponse;
      const pollerId = response?.id;

      if (!pollerId) {
        showErrorMessage(t(labelFailedToCreatePoller));

        return;
      }

      setPollerId(pollerId);

      setGeneratedCommand(response?.installation_command || '');
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
