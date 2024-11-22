import { useAtom } from 'jotai';

import { ListingPage } from '@centreon/ui';

import { DeleteConfirmationDialog } from './Actions/Delete';
import { DuplicationForm } from './Actions/Duplicate';
import Listing from './Listing';
import PageHeader from './PageHeader';
import Panel from './Panel';
import { isPanelOpenAtom } from './atom';

const Page = (): JSX.Element => {
  const [isPannelOpen] = useAtom(isPanelOpenAtom);

  return (
    <>
      <ListingPage
        filter={<PageHeader />}
        listing={<Listing />}
        panel={<Panel />}
        panelOpen={isPannelOpen}
      />
      <DeleteConfirmationDialog />
      <DuplicationForm />
    </>
  );
};

export default Page;
