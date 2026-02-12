import { useFetchQuery } from '@centreon/ui';

import { useAtom } from 'jotai';
import { equals, isNotNil, pick } from 'ramda';
import { useCallback, useEffect, useState } from 'react';

import { installationCommandDecoder } from '../../api/decoders';
import { getInstallationCommandEndpoint } from '../../api/endpoints';
import { pollerToGenerateCommanAtom } from '../../atoms';
import { InstallationCommand } from '../../models';

enum Os {
  windows = 'windows',
  linux = 'linux'
}

export const useInstallationCommand = () => {
  const [poller, setPoller] = useAtom(pollerToGenerateCommanAtom);
  const [state, setState] = useState({
    os: Os.windows,
    scriptCommand: ''
  });

  const isOpen = Boolean(poller);

  const close = useCallback(() => {
    setPoller(null);
  }, []);

  const changePoller = (_, value): void => {
    const selectedPoller = value ? pick(['id', 'name'], value) : {};

    setPoller(selectedPoller);
  };

  const { data, isLoading } = useFetchQuery<InstallationCommand>({
    decoder: installationCommandDecoder,
    getEndpoint: () => getInstallationCommandEndpoint(poller?.id),
    getQueryKey: () => ['installation-command', poller?.id],
    queryOptions: {
      enabled: isNotNil(poller?.id),
      suspense: false
    }
  });

  useEffect(() => {
    if (!data || isLoading) {
      return;
    }

    setState({
      os: state.os,
      scriptCommand: equals(state.os, Os.windows)
        ? data.windowsScriptCommand
        : data.linuxScriptCommand
    });
  }, [data?.id, state.os]);

  return {
    changePoller,
    close,
    isOpen,
    poller,
    setState,
    state
  };
};
