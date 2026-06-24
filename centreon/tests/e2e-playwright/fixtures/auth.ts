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

/**
 * Where the admin browser session is persisted by the `setup` project so the
 * specs that need full privileges (resources status, cloud notifications) can
 * reuse it through `test.use({ storageState })`.
 */
export const adminStorageStatePath = path.resolve(
  __dirname,
  '..',
  '.auth',
  'admin.json'
);
