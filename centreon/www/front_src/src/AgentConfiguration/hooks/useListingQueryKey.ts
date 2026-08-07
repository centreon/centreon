import { useAtomValue } from 'jotai';

import {
  filtersAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';

export const useListingQueryKey = (): Array<string | number> => {
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  return [
    'listAgentConfigurations',
    limit,
    page,
    sortField,
    sortOrder,
    JSON.stringify(filters)
  ];
};
