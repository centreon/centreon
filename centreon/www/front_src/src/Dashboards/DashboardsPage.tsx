import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { PageHeader, PageLayout } from '@centreon/ui/components';

import { labelDashboards } from './translatedLabels';
import { DashboardConfigModal } from './components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import { DashboardAccessRightsModal } from './components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import DashboardPageLayout from './components/DashboardPageLayout';
import DashboardNavbar from './components/DashboardNavbar/DashboardNavbar';
import DeleteDashboardModal from './components/DashboardLibrary/DeleteDashboardModal';
import DuplicateDashboardModal from './components/DashboardLibrary/DuplicateDashboardModal';

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
