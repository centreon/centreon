import { createSearchParams } from 'react-router';

import type { ResourceRow } from '../models';
import type { Filters } from './models';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'linked_service_templates_count',
  'parent_templates',
  'actions'
];

export const filtersInitialValues: Filters = {
  include_locked: false,
  name: ''
};

export const filtersAtomKey = 'filters_host_templates';
export const columnsAtomKey = 'columns_host_templates';

// Deep link opening a template's form on this page, not the legacy one.
export const getHostTemplateSearch = (id: number): string =>
  createSearchParams({ id: String(id), mode: 'edit' }).toString();

// A locked template cannot be selected, duplicated, deleted or changed.
export const isUnlocked = (row?: ResourceRow): boolean => !row?.isLocked;
