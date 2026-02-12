import { has } from 'ramda';

import { useWidgetGlobalContext } from '../WidgetContext';

const useIsOpenTicketInstalled = (): boolean => {
  const { platform } = useWidgetGlobalContext();

  const isOpenTicketInstalled = has('centreon-open-tickets', platform?.modules);

  return isOpenTicketInstalled;
};

export default useIsOpenTicketInstalled;
