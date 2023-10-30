import { ReactElement, Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import { PageHeader, PageLayout } from '@centreon/ui/components';

import { labelDashboardLibrary } from './translatedLabels';
import { DashboardsOverviewSkeleton } from './components/DashboardsOverview/DashboardsOverviewSkeleton';
import { DashboardConfigModal } from './components/DashboardConfig/DashboardConfigModal';
import { DashboardAccessRightsModal } from './components/DashboardAccessRights/DashboardAccessRightsModal';
import { DashboardsOverview } from './components/DashboardsOverview/DashboardsOverview';

const DashboardsPage = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelDashboardLibrary)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Suspense fallback={<DashboardsOverviewSkeleton />}>
          <DashboardsOverview />
        </Suspense>
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export { DashboardsPage };
