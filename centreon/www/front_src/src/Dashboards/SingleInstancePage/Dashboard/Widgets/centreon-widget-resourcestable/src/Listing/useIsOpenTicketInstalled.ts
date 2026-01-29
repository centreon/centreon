import { platformVersionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { has } from 'ramda';

const useIsOpenTicketInstalled = (): boolean => {
  const platform = useAtomValue(platformVersionsAtom);

  const isOpenTicketInstalled = has('centreon-open-tickets', platform?.modules);

  return isOpenTicketInstalled;
};

export default useIsOpenTicketInstalled;
