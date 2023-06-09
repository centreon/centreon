import React, { ReactElement } from 'react';

import {
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';

import { IconButton, PageHeader, PageLayout } from '@centreon/ui/components';

import { DashboardsQuickAccessMenu } from '../components/DashboardsQuickAccessMenu/DashboardsQuickAccessMenu';
import { DashboardFormModal } from '../components/DashboardFormModal/DashboardFormModal';
import { useDashboardForm } from '../components/DashboardFormModal/useDashboardForm';
import { Dashboard as DashboardType } from '../api/models';
import { DashboardAccessRightsModal } from '../components/DashboardAccessRightsModal/DashboardAccessRightsModal';
import { useDashboardAccessRights } from '../components/DashboardAccessRightsModal/useDashboardAccessRights';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './useDashboardDetails';
import HeaderActions from './HeaderActions';

const Dashboard = (): ReactElement => {
  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardForm();
  const { editAccessRights } = useDashboardAccessRights();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Menu>
              <DashboardsQuickAccessMenu dashboard={dashboard} />
            </PageHeader.Menu>
            <PageHeader.Title
              description={dashboard?.description || ''}
              title={dashboard?.name || ''}
            />
          </PageHeader.Main>
          <PageHeader.Actions>
            <HeaderActions
              id={dashboard?.id}
              name={dashboard?.name}
              panels={panels}
            />
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <PageLayout.Actions>
          <IconButton
            aria-label="edit"
            data-testid="edit"
            icon={<SettingsIcon />}
            size="small"
            variant="ghost"
            onClick={editDashboard(dashboard as DashboardType)}
          />
          <IconButton
            aria-label="share"
            data-testid="share"
            icon={<ShareIcon />}
            size="small"
            variant="ghost"
            onClick={editAccessRights(dashboard)}
          />
        </PageLayout.Actions>

        <Layout />
      </PageLayout.Body>
      <DashboardFormModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export default Dashboard;
