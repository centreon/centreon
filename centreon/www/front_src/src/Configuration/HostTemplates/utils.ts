import { createSearchParams } from 'react-router';

import type { Filters } from './models';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'linked_service_templates_count',
  'parent_templates',
  'actions'
];

// Unchecked sends nothing: the endpoint then lists the unlocked templates only.
export const filtersInitialValues: Filters = {
  is_locked: false,
  name: ''
};

export const filtersAtomKey = 'filters_host_templates';
export const columnsAtomKey = 'columns_host_templates';

// The deep link `ConfigurationBase` opens a row's form from, so a parent
// template opens on this page rather than in the legacy form.
export const getHostTemplateSearch = (id: number): string =>
  createSearchParams({ id: String(id), mode: 'edit' }).toString();
