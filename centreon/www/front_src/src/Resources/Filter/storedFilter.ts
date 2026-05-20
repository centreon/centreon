// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { baseKey, getStoredOrDefault } from '../storage';
import { Filter } from './models';

const filterKey = `${baseKey}filter`;

let cachedFilter: unknown;

const getStoredOrDefaultFilter = (defaultValue: Filter): Filter => {
  return getStoredOrDefault<Filter>({
    cachedItem: cachedFilter,
    defaultValue,
    key: filterKey,
    onCachedItemUpdate: (updatedItem) => {
      cachedFilter = updatedItem;
    }
  });
};

export { getStoredOrDefaultFilter };
