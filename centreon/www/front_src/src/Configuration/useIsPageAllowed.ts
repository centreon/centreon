import { flatten, includes } from 'ramda';

import useNavigation from '../Navigation/useNavigation';

// Whether the menu grants a React page to the user: its topology row exists,
// its feature flag (if any) is on, and the menu ACL allows it. A page that is
// not shipped yet has no row, so links to it keep their legacy target.
export const useIsPageAllowed = (path: string): boolean => {
  const { allowedPages } = useNavigation();

  return includes(path, flatten(allowedPages ?? []));
};
