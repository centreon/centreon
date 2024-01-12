export const defaultSelectedColumnIds = [
  'status',
  'resource',
  'parent_resource',
  'state',
  'severity',
  'duration',
  'last_check'
];

export const defaultSelectedColumnIdsforViewByHost = [
  'status',
  'resource',
  // 'services',
  'state',
  'severity',
  'duration',
  'last_check'
];

export { default as useColumns } from './useColumns';
