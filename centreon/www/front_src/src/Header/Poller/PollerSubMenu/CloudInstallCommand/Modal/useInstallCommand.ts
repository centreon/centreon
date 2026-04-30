import {
  Method,
  centreonBaseURL,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { useAtom, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { createPollerEndpoint } from '../../../../api/endpoints';
import { labelFailedToCreatePoller } from '../../../translatedLabels';
import { generatedCommandAtom, isModalOpenAtom, pollerIdAtom } from '../atoms';
import type { CloudInstallCommandFormValues } from '../models';

interface UseInstallCommandState {
  submit: (values: CloudInstallCommandFormValues) => Promise<void>;
  close: () => void;
  isOpen: boolean;
}

const dummyCommand =
  'curl -fsSL https://<CENTRAL_URL>/poller/install.sh | bash -s -- --poller_token fedIjfgDSige.gzfhgyz --uuid 1234-abcd --name paris-edge-01 --type onprem/docker --central_url <CENTRAL_URL>';

export const useInstallCommand = (): UseInstallCommandState => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useAtom(isModalOpenAtom);
  const setGeneratedCommand = useSetAtom(generatedCommandAtom);
  const setPollerId = useSetAtom(pollerIdAtom);

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
      const pollerResponse = await createPoller({
        payload: {
          address: values.pollerAddress.trim(),
          name: values.pollerName.trim(),
          poller_type: values.environment,
          token: values?.token?.name
        }
      });

      const pollerId = 1; //pollerResponse?.id;

      if (!pollerId) {
        showErrorMessage(t(labelFailedToCreatePoller));

        return;
      }

      setPollerId(pollerId);

      const centralUrl = `${window.location.origin}${centreonBaseURL}`;
      const command = (pollerResponse?.command || dummyCommand).replaceAll(
        '<CENTRAL_URL>',
        centralUrl
      );

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
