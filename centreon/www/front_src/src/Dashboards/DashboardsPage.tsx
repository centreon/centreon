import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { PageHeader, PageLayout } from '@centreon/ui/components';

import { DashboardAccessRightsModal } from './components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import { DashboardConfigModal } from './components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import DeleteDashboardModal from './components/DashboardLibrary/DeleteDashboardModal';
import DuplicateDashboardModal from './components/DashboardLibrary/DuplicateDashboardModal';
import DashboardNavbar from './components/DashboardNavbar/DashboardNavbar';
import DashboardPageLayout from './components/DashboardPageLayout';
import { labelDashboards } from './translatedLabels';

const DashboardsPage = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelDashboards)} />
          </PageHeader.Main>
          <PageHeader.Actions>
            <DashboardNavbar />
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <DashboardPageLayout />
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
      <DeleteDashboardModal />
      <DuplicateDashboardModal />
    </PageLayout>
  );
};

export { DashboardsPage };
