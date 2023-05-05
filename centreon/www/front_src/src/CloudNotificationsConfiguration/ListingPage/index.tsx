import { useAtom } from 'jotai';

import { ListingPage } from '@centreon/ui';

import PanelEdit from '../EditPanel';
import { isPanelOpenAtom } from '../atom';
import Listing from '../Listing';

import ListingPageHeader from './ListingPageHeader';

const NotificationsListingPage = (): JSX.Element => {
  const [isPannelOpen] = useAtom(isPanelOpenAtom);

  return (
    <div>
      <ListingPage
        fullHeight
        filter={<ListingPageHeader />}
        listing={<Listing />}
        panel={<PanelEdit />}
        panelOpen={isPannelOpen}
      />
    </div>
  );
};

export default NotificationsListingPage;
