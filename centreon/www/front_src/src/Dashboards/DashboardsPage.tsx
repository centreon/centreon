import { ReactElement, Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import { PageHeader, PageLayout } from '@centreon/ui/components';

import { labelDashboards } from './translatedLabels';
import { DashboardsOverviewSkeleton } from './components/DashboardLibrary/DashboardsOverview/DashboardsOverviewSkeleton';
import { DashboardConfigModal } from './components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import { DashboardAccessRightsModal } from './components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import DashboardPageLayout from './components/DashboardPageLayout';
import DashboardNavbar from './components/DashboardNavbar/DashboardNavbar';

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
        <Suspense fallback={<DashboardsOverviewSkeleton />}>
          <DashboardPageLayout />
        </Suspense>
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export { DashboardsPage };
