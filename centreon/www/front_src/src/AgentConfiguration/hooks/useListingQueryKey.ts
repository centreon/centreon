import { useAtomValue } from 'jotai';
import { limitAtom, pageAtom, searchAtom } from '../atoms';

export const useListingQueryKey = (): Array<string | number> => {
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const search = useAtomValue(searchAtom);

  return ['agent-configurations', limit, page, search];
};
