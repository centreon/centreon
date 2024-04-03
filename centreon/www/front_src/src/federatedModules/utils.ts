import { createStore } from 'jotai';

import { centreonBaseURL } from '@centreon/ui';

interface LoadScriptProps {
  scriptPath: string;
  store: ReturnType<typeof createStore>;
}

export const loadScript = async ({
  scriptPath,
  store
}: LoadScriptProps): Promise<void> => {
  const timestamp = `?t=${new Date().getTime()}`;

  const formattedScriptPath = scriptPath.replace('.', `/${centreonBaseURL}`);

  const filePath = `${window.location.origin}${formattedScriptPath}.js`;

  const { main } = await import(
    /* webpackIgnore: true */ `${filePath}${timestamp}`
  );

  main({ store });
};
