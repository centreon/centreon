import { useAtom } from 'jotai';

import { Box } from '@mui/material';

import { ListingPage } from '@centreon/ui';

import PanelEdit from '../EditPanel';
import { isPanelOpenAtom } from '../atom';
import Listing from '../Listing';
import { DeleteConfirmationDialog } from '../Actions/delete';
import { DuplicationForm } from '../Actions/duplicate';

import ListingPageHeader from './ListingPageHeader';

const NotificationsListingPage = (): JSX.Element => {
  const [isPannelOpen] = useAtom(isPanelOpenAtom);

  return (
    <Box>
      <ListingPage
        fullHeight
        filter={<ListingPageHeader />}
        listing={<Listing />}
        panel={<PanelEdit />}
        panelOpen={isPannelOpen}
      />
      <DeleteConfirmationDialog />
      <DuplicationForm />
    </Box>
  );
};

export default NotificationsListingPage;
