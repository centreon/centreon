import { ReactElement } from 'react';

import { useAtomValue } from 'jotai';

import {
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';

import { IconButton, PageHeader, PageLayout } from '@centreon/ui/components';

import { DashboardsQuickAccessMenu } from '../components/DashboardsQuickAccess/DashboardsQuickAccessMenu';
import { DashboardConfigModal } from '../components/DashboardConfig/DashboardConfigModal';
import { useDashboardConfig } from '../components/DashboardConfig/useDashboardConfig';
import { Dashboard as DashboardType } from '../api/models';
import { DashboardAccessRightsModal } from '../components/DashboardAccessRights/DashboardAccessRightsModal';
import { useDashboardAccessRights } from '../components/DashboardAccessRights/useDashboardAccessRights';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './useDashboardDetails';
import { isEditingAtom } from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import { AddWidgetButton } from './AddEditWidget';
import { editProperties } from './useCanEditDashboard';

const Dashboard = (): ReactElement => {
  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardConfig();
  const { editAccessRights } = useDashboardAccessRights();

  const isEditing = useAtomValue(isEditingAtom);

  const { canEdit } = editProperties.useCanEditProperties();

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
            {canEdit && <DashboardEditActions panels={panels} />}
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <PageLayout.Actions>
          <span>
            {!isEditing && canEdit && (
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
                  onClick={editAccessRights(dashboard as DashboardType)}
                />
              </>
            )}
          </span>
          <span>{canEdit && <AddWidgetButton />}</span>
        </PageLayout.Actions>

        <Layout />
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export { Dashboard };
