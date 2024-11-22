import { useAtomValue } from 'jotai';

import { isOnPublicPageAtom } from '@centreon/ui-context';

import PanelsLayout from './Layout/Layout';
import { dashboardAtom } from './atoms';
import useDashboardDetails from './hooks/useDashboardDetails';

interface Props {
  displayedDashboardId: number;
  playlistHash?: string;
}

const DashboardLayout = ({
  displayedDashboardId,
  playlistHash
}: Props): JSX.Element => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const dashboard = useAtomValue(dashboardAtom);

  useDashboardDetails({
    dashboardId: displayedDashboardId,
    isOnPublicPage,
    playlistHash,
    suspense: false,
    viewOnly: true
  });

  return (
    <PanelsLayout
      isStatic
      canEdit={false}
      dashboardId={displayedDashboardId}
      displayMoreActions={false}
      panels={dashboard.layout}
      playlistHash={playlistHash}
    />
  );
};

export default DashboardLayout;
