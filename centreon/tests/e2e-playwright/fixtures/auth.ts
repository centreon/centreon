import path from 'node:path';

/**
 * Where the dashboard-creator browser session is persisted by the `setup`
 * project so the dashboard specs can reuse it (via `test.use({ storageState }`))
 * instead of logging in through the UI in every test.
 */
export const creatorStorageStatePath = path.resolve(
  __dirname,
  '..',
  '.auth',
  'dashboard-creator.json'
);
