import { Header, TiledListingPage } from '@centreon/ui';

import Layout from './Layout';
import Toolbar from './Toolbar';
import useDashboardDetails from './useDashboardDetails';
import HeaderActions from './HeaderActions';

const Dashboard = (): JSX.Element => {
  const { dashboard, panels } = useDashboardDetails();

  return (
    <TiledListingPage>
      <Header title={dashboard?.name || ''} nav={<HeaderActions />} />
      <Layout />
    </TiledListingPage>
  );
};

export default Dashboard;
