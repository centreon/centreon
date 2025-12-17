import { useAtomValue } from 'jotai';

import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from '../atoms';

export const useListingQueryKey = (): Array<string | number> => {
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);

  return ['listAgentConfigurations', limit, page, sortField, sortOrder];
};
