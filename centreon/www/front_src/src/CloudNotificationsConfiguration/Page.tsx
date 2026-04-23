import { ListingPage } from '@centreon/ui';

import { useAtom } from 'jotai';

import { DeleteConfirmationDialog } from './Actions/Delete';
import { DuplicationForm } from './Actions/Duplicate';
import { isPanelOpenAtom } from './atom';
import Listing from './Listing';
import PageHeader from './PageHeader';
import Panel from './Panel';

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
