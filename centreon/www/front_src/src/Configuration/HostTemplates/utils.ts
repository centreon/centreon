import type { Filters } from './models';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'templates',
  'serviceTemplatesCount',
  'actions'
];

// Locked templates are hidden until asked for, as the legacy page does: the
// monitoring connectors bring hundreds of them.
export const filtersInitialValues: Filters = {
  isLockedShown: false,
  name: ''
};

export const filtersAtomKey = 'filters_host_templates';
export const columnsAtomKey = 'columns_host_templates';
