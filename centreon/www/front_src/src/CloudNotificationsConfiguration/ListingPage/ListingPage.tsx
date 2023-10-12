import { useAtom } from 'jotai';

import { ListingPage } from '@centreon/ui';

import Panel from '../Panel';
import { isPanelOpenAtom } from '../atom';
import Listing from '../Listing';
import { DeleteConfirmationDialog } from '../Actions/Delete';
import { DuplicationForm } from '../Actions/Duplicate';

import ListingPageHeader from './ListingPageHeader';

const NotificationsListingPage = (): JSX.Element => {
  const [isPannelOpen] = useAtom(isPanelOpenAtom);

  return (
    <>
      <ListingPage
        filter={<ListingPageHeader />}
        listing={<Listing />}
        panel={<Panel />}
        panelOpen={isPannelOpen}
      />
      <DeleteConfirmationDialog />
      <DuplicationForm />
    </>
  );
};

export default NotificationsListingPage;
