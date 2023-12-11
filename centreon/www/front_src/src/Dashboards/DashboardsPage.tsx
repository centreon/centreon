import { ReactElement, Suspense } from 'react';

import { useTranslation } from 'react-i18next';
import { always, cond, equals } from 'ramda';

import { PageHeader, PageLayout } from '@centreon/ui/components';

import { labelDashboardLibrary, labelPlaylists } from './translatedLabels';
import { DashboardsOverviewSkeleton } from './components/DashboardLibrary/DashboardsOverview/DashboardsOverviewSkeleton';
import { DashboardConfigModal } from './components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import { DashboardAccessRightsModal } from './components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import DashboardPageLayout from './components/DashboardPageLayout';
import DashboardNavbar from './components/DashboardNavbar/DashboardNavbar';
import { DashboardLayout } from './models';
import { routerHooks } from './routerHooks';
import DashboardsPlaylistSkeleton from './components/DashboardPlaylists/DashboardsPlaylistSkeleton';

const getTitle = cond([
  [equals(DashboardLayout.Library), always(labelDashboardLibrary)],
  [equals(DashboardLayout.Playlist), always(labelPlaylists)]
]);

const DashboardsPage = (): ReactElement => {
  const { t } = useTranslation();
  const { layout } = routerHooks.useParams();

  const fallback = equals(layout, DashboardLayout.Library) ? (
    <DashboardsOverviewSkeleton />
  ) : (
    <DashboardsPlaylistSkeleton />
  );

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(getTitle(layout as DashboardLayout))} />
          </PageHeader.Main>
          <PageHeader.Actions>
            <DashboardNavbar />
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Suspense fallback={fallback}>
          <DashboardPageLayout />
        </Suspense>
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export { DashboardsPage };
