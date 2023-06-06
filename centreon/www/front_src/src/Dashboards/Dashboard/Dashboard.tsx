import { useMemo } from 'react';

import { TiledListingPage } from '@centreon/ui';
import { Header } from '@centreon/ui/components';

import useUserDashboardPermissions from '../useUserDashboardPermissions';

import Layout from './Layout';
import useDashboardDetails from './useDashboardDetails';
import HeaderActions from './HeaderActions';

const Dashboard = (): JSX.Element => {
  const { dashboard, panels } = useDashboardDetails();

  const { hasEditPermission } = useUserDashboardPermissions();

  const canEdit = useMemo(
    () => dashboard && hasEditPermission(dashboard),
    [dashboard]
  );

  return (
    <TiledListingPage>
      <Header
        nav={
          canEdit && (
            <HeaderActions
              id={dashboard?.id}
              name={dashboard?.name}
              panels={panels}
            />
          )
        }
        title={dashboard?.name || ''}
      />
      <Layout />
    </TiledListingPage>
  );
};

export default Dashboard;
