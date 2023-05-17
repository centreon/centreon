import { Header, TiledListingPage } from '@centreon/ui';

import Layout from './Layout';
import Toolbar from './Toolbar';
import useDashboardDetails from './useDashboardDetails';

const Dashboard = (): JSX.Element => {
  const { dashboard } = useDashboardDetails();

  return (
    <TiledListingPage>
      <Header title={dashboard?.name || ''} />
      <Layout />
    </TiledListingPage>
  );
};

export default Dashboard;
