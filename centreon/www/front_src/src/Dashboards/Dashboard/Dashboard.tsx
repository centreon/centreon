import { Header, TiledListingPage } from '@centreon/ui';

import Layout from './Layout';
import useDashboardDetails from './useDashboardDetails';
import HeaderActions from './HeaderActions';

const Dashboard = (): JSX.Element => {
  const { dashboard } = useDashboardDetails();

  return (
    <TiledListingPage>
      <Header nav={<HeaderActions />} title={dashboard?.name || ''} />
      <Layout />
    </TiledListingPage>
  );
};

export default Dashboard;
