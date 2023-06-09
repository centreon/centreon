import React, { ReactElement } from 'react';

import { useAtomValue } from 'jotai';

import {
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';
import AddIcon from '@mui/icons-material/Add';

import {
  Button,
  IconButton,
  PageHeader,
  PageLayout
} from '@centreon/ui/components';

import { DashboardsQuickAccessMenu } from '../components/DashboardsQuickAccessMenu/DashboardsQuickAccessMenu';
import { DashboardFormModal } from '../components/DashboardFormModal/DashboardFormModal';
import { useDashboardForm } from '../components/DashboardFormModal/useDashboardForm';
import { Dashboard as DashboardType } from '../api/models';
import { DashboardAccessRightsModal } from '../components/DashboardAccessRightsModal/DashboardAccessRightsModal';
import { useDashboardAccessRights } from '../components/DashboardAccessRightsModal/useDashboardAccessRights';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './useDashboardDetails';
import HeaderActions from './HeaderActions';
import { isEditingAtom } from './atoms';

const Dashboard = (): ReactElement => {
  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardForm();
  const { editAccessRights } = useDashboardAccessRights();

  const isEditing = useAtomValue(isEditingAtom);

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
          <span>
            {!isEditing && (
              <>
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
              </>
            )}
          </span>
          <span>
            {isEditing && (
              <Button
                aria-label="create"
                data-testid="create-dashboard"
                icon={<AddIcon />}
                iconVariant="start"
                size="small"
              >
                Add a panel
              </Button>
            )}
          </span>
        </PageLayout.Actions>

        <Layout />
      </PageLayout.Body>
      <DashboardFormModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export default Dashboard;
