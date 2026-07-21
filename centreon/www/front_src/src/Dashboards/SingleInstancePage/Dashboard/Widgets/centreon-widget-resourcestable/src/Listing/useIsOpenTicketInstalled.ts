import { useAtomValue } from 'jotai';
import { has } from 'ramda';

import { platformLocalAtom } from '../atom';

const useIsOpenTicketInstalled = (): boolean => {
  const platform = useAtomValue(platformLocalAtom);

  const isOpenTicketInstalled = has('centreon-open-tickets', platform?.modules);

  return isOpenTicketInstalled;
};

export default useIsOpenTicketInstalled;
