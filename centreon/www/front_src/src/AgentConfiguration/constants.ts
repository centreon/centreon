import { replace } from 'ramda';

const removePathRoot = replace(/^.*configuration\//, '');
const normalizePath = replace(/\//g, '_');

export const filtersAtomKey = `filters_${normalizePath(removePathRoot(window.location.pathname))}`;
export const columnsAtomKey = `columns_${normalizePath(removePathRoot(window.location.pathname))}`;
