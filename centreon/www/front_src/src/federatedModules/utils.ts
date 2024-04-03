import { centreonBaseURL } from '@centreon/ui';

export const loadScript = async (scriptPath: string): Promise<void> => {
  const timestamp = `?t=${new Date().getTime()}`;

  const formattedScriptPath = scriptPath.replace('.', `/${centreonBaseURL}`);

  const filePath = `${window.location.origin}${formattedScriptPath}.js`;

  const { main } = await import(
    /* webpackIgnore: true */ `${filePath}${timestamp}`
  );

  // TO CHANGE: test purpose
  main('test');
};
