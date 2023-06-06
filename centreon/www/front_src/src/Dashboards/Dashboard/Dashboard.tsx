import { useMemo } from 'react';

import { TiledListingPage } from '@centreon/ui';
import { Header } from '@centreon/ui/components';

import useUserDashboardPermissions from '../useUserDashboardPermissions';

import Layout from './Layout';
import useDashboardDetails from './useDashboardDetails';
import HeaderActions from './HeaderActions/HeaderActions';

const Dashboard = (): JSX.Element => {
  const { dashboard, panels } = useDashboardDetails();

  const { hasEditPermission } = useUserDashboardPermissions();

  const canEdit = useMemo(
    () => dashboard && hasEditPermission(dashboard),
    [dashboard]
  );

  return (
    <TiledListingPage>
      <Header title={dashboard?.name || ''} />
      {canEdit && (
        <HeaderActions
          id={dashboard?.id}
          name={dashboard?.name}
          panels={panels}
        />
      )}
      <Layout />
    </TiledListingPage>
  );
};

export default Dashboard;
