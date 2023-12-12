import { useAtomValue } from 'jotai';

import { Dashboard } from '../../../components/DashboardPlaylists/models';
import useDashboardDetails from '../../Dashboard/hooks/useDashboardDetails';
import { displayedDashboardAtom } from '../atoms';
import useRotationDashboards from '../hooks/useRotateDashboards';
import PanelsLayout from '../../Dashboard/Layout/Layout';
import { dashboardAtom } from '../../Dashboard/atoms';

interface Props {
  dashboards: Array<Dashboard>;
  playlistId: number;
  rotationTime: number;
}

const PlaylistBody = ({
  playlistId,
  rotationTime,
  dashboards
}: Props): JSX.Element => {
  const displayedDashboardId = useAtomValue(displayedDashboardAtom);
  const dashboard = useAtomValue(dashboardAtom);

  useRotationDashboards({ dashboards, playlistId, rotationTime });

  useDashboardDetails({
    dashboardId: displayedDashboardId,
    suspense: false,
    viewOnly: true
  });

  return (
    <PanelsLayout
      isStatic
      displayMoreActions={false}
      isEditing={false}
      panels={dashboard.layout}
    />
  );
};

export default PlaylistBody;
