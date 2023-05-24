import { Header, TiledListingPage } from '@centreon/ui';

import Layout from './Layout';
import useDashboardDetails from './useDashboardDetails';
import HeaderActions from './HeaderActions';

const Dashboard = (): JSX.Element => {
  const { dashboard, panels } = useDashboardDetails();

  return (
    <TiledListingPage>
      <Header
        nav={
          <HeaderActions
            id={dashboard?.id}
            name={dashboard?.name}
            panels={panels}
          />
        }
        title={dashboard?.name || ''}
      />
      <Layout />
    </TiledListingPage>
  );
};

export default Dashboard;
