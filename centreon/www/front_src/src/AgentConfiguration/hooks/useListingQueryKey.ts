import { useAtomValue } from 'jotai';
import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';

export const useListingQueryKey = (): Array<string | number> => {
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const search = useAtomValue(searchAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);

  return ['agent-configurations', limit, page, search, sortField, sortOrder];
};
