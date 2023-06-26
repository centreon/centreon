import { ReactElement, useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

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

import { DashboardsQuickAccessMenu } from '../components/DashboardsQuickAccess/DashboardsQuickAccessMenu';
import { DashboardConfigModal } from '../components/DashboardConfig/DashboardConfigModal';
import { useDashboardConfig } from '../components/DashboardConfig/useDashboardConfig';
import { Dashboard as DashboardType } from '../api/models';
import { DashboardAccessRightsModal } from '../components/DashboardAccessRights/DashboardAccessRightsModal';
import { useDashboardAccessRights } from '../components/DashboardAccessRights/useDashboardAccessRights';
import { useDashboardUserPermissions } from '../components/DashboardUserPermissions/useDashboardUserPermissions';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './useDashboardDetails';
import HeaderActions from './HeaderActions';
import { isEditingAtom } from './atoms';
import { labelAddAWidget } from './translatedLabels';

const Dashboard = (): ReactElement => {
  const { t } = useTranslation();

  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardConfig();
  const { editAccessRights } = useDashboardAccessRights();

  const isEditing = useAtomValue(isEditingAtom);

  const { hasEditPermission } = useDashboardUserPermissions();

  const canEdit = useMemo(
    () => dashboard && hasEditPermission(dashboard),
    [dashboard]
  );

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
            {canEdit && (
              <HeaderActions
                id={dashboard?.id}
                name={dashboard?.name}
                panels={panels}
              />
            )}
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
                  onClick={editAccessRights(dashboard as DashboardType)}
                />
              </>
            )}
          </span>
          <span>
            {isEditing && (
              <Button
                aria-label="add widget"
                data-testid="add-widget"
                icon={<AddIcon />}
                iconVariant="start"
                size="small"
              >
                {t(labelAddAWidget)}
              </Button>
            )}
          </span>
        </PageLayout.Actions>

        <Layout />
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export { Dashboard };
