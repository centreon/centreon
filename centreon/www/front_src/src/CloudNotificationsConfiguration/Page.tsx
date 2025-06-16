import { useAtom } from 'jotai';

import { ListingPage } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import { labelTLS } from '../AgentConfiguration/translatedLabels';
import { DeleteConfirmationDialog } from './Actions/Delete';
import { DuplicationForm } from './Actions/Duplicate';
import Listing from './Listing';
import PageHeader from './PageHeader';
import Panel from './Panel';
import { isPanelOpenAtom } from './atom';

const Page = (): JSX.Element => {
  const { t } = useTranslation();

  const [isPannelOpen] = useAtom(isPanelOpenAtom);

  return (
    <>
      <div>{t(labelTLS)}</div>
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
