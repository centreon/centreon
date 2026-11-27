// Name the export endpoint gives to a generated export (file name without extension)
const exportFileNamePattern = /^centreon-clapi-export-\d+$/;

// Folder where the export text file and its archive are written in the web container
const exportFolder = '/tmp';

// A file name that leaves the export folder
const traversalFileName = '../../../etc/passwd';

const buildSetServiceAccountQuery = (contactAlias: string): string =>
  `UPDATE contact SET is_service_account = 1 WHERE contact_alias = '${contactAlias}'`;

// The AWIE endpoints answer JSON with a text content type
const parseJsonBody = (body: unknown): Record<string, unknown> => {
  if (typeof body !== 'string') {
    return (body ?? {}) as Record<string, unknown>;
  }

  try {
    return JSON.parse(body) as Record<string, unknown>;
  } catch {
    return {};
  }
};

export {
  buildSetServiceAccountQuery,
  exportFileNamePattern,
  exportFolder,
  parseJsonBody,
  traversalFileName
};
