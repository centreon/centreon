import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';

import { dashboardAtom } from './atoms';
import useDashboardDetails from './hooks/useDashboardDetails';
import PanelsLayout from './Layout/Layout';

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
      canEdit={false}
      dashboardId={displayedDashboardId}
      displayMoreActions={false}
      isStatic
      panels={dashboard.layout}
      playlistHash={playlistHash}
    />
  );
};

export default DashboardLayout;
