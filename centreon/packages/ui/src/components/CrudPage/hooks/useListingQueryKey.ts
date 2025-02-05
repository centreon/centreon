import { useAtomValue } from 'jotai';
import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import { UseListingQueryKeyProps } from '../models';

export const useListingQueryKey = <TFilter>({
  filtersAtom,
  queryKeyName
}: UseListingQueryKeyProps<TFilter>): Array<string | number> => {
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const search = useAtomValue(searchAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const filters = useAtomValue(filtersAtom);

  return [
    queryKeyName,
    limit,
    page,
    search,
    sortField,
    sortOrder,
    JSON.stringify(filters)
  ];
};
