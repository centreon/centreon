import { useAtom } from 'jotai';

import { Box } from '@mui/material';

import { ListingPage } from '@centreon/ui';

import Panel from './Panel';
import Listing from './Listing';
import PageHeader from './PageHeader';
import { isPanelOpenAtom } from './atom';
import { DeleteConfirmationDialog } from './Actions/Delete';
import { DuplicationForm } from './Actions/Duplicate';

const Page = (): JSX.Element => {
  const [isPannelOpen] = useAtom(isPanelOpenAtom);

  return (
    <Box>
      <ListingPage
        fullHeight
        filter={<PageHeader />}
        listing={<Listing />}
        panel={<Panel />}
        panelOpen={isPannelOpen}
      />
      <DeleteConfirmationDialog />
      <DuplicationForm />
    </Box>
  );
};

export default Page;
