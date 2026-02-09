import { useAtom } from 'jotai';
import { useCallback, useEffect, useState } from 'react';

import { useFetchQuery } from '@centreon/ui';

import { pollerToGenerateCommanAtom } from '../../atoms';

import { equals, isNotNil, pick } from 'ramda';

import { installationCommandDecoder } from '../../api/decoders';
import { getInstallationCommandEndpoint } from '../../api/endpoints';
import { InstallationCommand } from '../../models';

enum Os {
  windows = 'windows',
  linux = 'linux'
}

export const useInstallationCommand = () => {
  const [poller, setPoller] = useAtom(pollerToGenerateCommanAtom);
  const [state, setState] = useState({
    os: Os.windows,
    scriptCommand: '',
    scriptUrl: ''
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
        : data.linuxScriptCommand,
      scriptUrl: equals(state.os, Os.windows)
        ? data.windowsScriptURL
        : data.linuxScriptURL
    });
  }, [data?.id, state.os]);

  return {
    isOpen,
    close,
    state,
    setState,
    changePoller,
    poller
  };
};
