import { useAtomValue } from 'jotai';

import { dashboardAtom } from './atoms';
import useDashboardDetails from './hooks/useDashboardDetails';
import PanelsLayout from './Layout/Layout';

interface Props {
  displayedDashboardId: number;
}

const DashboardLayout = ({ displayedDashboardId }: Props): JSX.Element => {
  const dashboard = useAtomValue(dashboardAtom);

  useDashboardDetails({
    dashboardId: displayedDashboardId,
    suspense: false,
    viewOnly: true
  });

  return <PanelsLayout isStatic panels={dashboard.layout} />;
};

export default DashboardLayout;
