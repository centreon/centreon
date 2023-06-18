import { ReactElement, Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import { PageHeader, PageLayout } from '@centreon/ui/components';

import { labelDashboards } from './translatedLabels';
import { ListingSkeleton } from './ListingSkeleton';
import Listing from './Listing';
import { DashboardConfigModal } from './components/DashboardConfig/DashboardConfigModal';
import { DashboardAccessRightsModal } from './components/DashboardAccessRights/DashboardAccessRightsModal';
import { DashboardDeleteConfirmationModal } from './components/DashboardDelete/DashboardDeleteConfirmationModal';

const DashboardsPage = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelDashboards)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Suspense fallback={<ListingSkeleton />}>
          <Listing />
        </Suspense>
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
      <DashboardDeleteConfirmationModal />
    </PageLayout>
  );
};

export { DashboardsPage };
