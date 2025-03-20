import { replace } from 'ramda';

const removePathRoot = replace(/^.*configuration\//, '');
const normalizePath = replace(/\//g, '_');

export const atomKey = `${normalizePath(removePathRoot(window.location.pathname))}`;
