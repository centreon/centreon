import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';

import { detailsAtom } from '../../detailsAtoms';

import ContactsLoadingSkeleton from './ContactsLoadingSkeleton';
import Notifications from './Notifications';

const NotificationTab = (): JSX.Element => {
  const details = useAtomValue(detailsAtom);

  const loading = isNil(details);

  if (loading) {
    return <ContactsLoadingSkeleton />;
  }

  return <Notifications />;
};

export default NotificationTab;
