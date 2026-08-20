import { spawnSync } from 'node:child_process';

import {
  composeFile,
  composeProjectName,
  platformVersionsUrl,
  webImage
} from '../config/environment';

const startupTimeout = 15 * 60 * 1000;
const readinessTimeout = 5 * 60 * 1000;
const readinessInterval = 2000;

const runCompose = (args: Array<string>, timeout: number): void => {
  const { error, status, stderr } = spawnSync(
    'docker',
    [
      'compose',
      '--file',
      composeFile,
      '--project-name',
      composeProjectName,
      ...args
    ],
    {
      encoding: 'utf8',
      env: { ...process.env, WEB_IMAGE: webImage },
      stdio: ['ignore', 'inherit', 'pipe'],
      timeout
    }
  );

  if (error) {
    throw error;
  }

  if (status !== 0) {
    throw new Error(
      `docker compose ${args.join(' ')} failed with status ${status}\n${stderr ?? ''}`
    );
  }

  if (stderr) {
    process.stderr.write(stderr);
  }
};

const waitForPlatform = async (): Promise<void> => {
  const deadline = Date.now() + readinessTimeout;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(platformVersionsUrl);

      if (response.ok) {
        return;
      }
    } catch {
      // The web container is not accepting connections yet.
    }

    await new Promise((resolve) => setTimeout(resolve, readinessInterval));
  }

  throw new Error(`Centreon platform not ready on ${platformVersionsUrl}`);
};

export const startStack = async (): Promise<void> => {
  runCompose(['up', '--detach', '--wait', 'web'], startupTimeout);

  await waitForPlatform();
};

export const stopStack = (): void => {
  runCompose(['down', '--volumes', '--remove-orphans'], startupTimeout);
};
