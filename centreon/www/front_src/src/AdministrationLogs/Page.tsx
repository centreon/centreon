import { useInfiniteScrollListing } from '@centreon/ui';
import { PageHeader, PageLayout } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { activityLogsDecoder } from './api/decoders';
import { activityLogsEndpoint } from './api/endpoints';
import { dateSortOrderAtom, pageAtom } from './atoms';
import Filters from './Filters/Filters';
import PeriodFilter from './Filters/PeriodFilter';
import Listing from './Listing/Listing';
import type { ActivityLog } from './models';
import SidePanelLayout from './SidePanel/SidePanelLayout';
import { labelLogs, labelLogsDescription } from './translatedLabels';
import { useActivityLogsQueryParameters } from './useActivityLogsQueryParameters';
import { useSelectedLog } from './useSelectedLog';
import {
  useSelectedTarget,
  useSyncSelectedTargetWithUrl
} from './useSelectedTarget';

const AdministrationLogsPage = (): JSX.Element => {
  const { t } = useTranslation();

  const dateSortOrder = useAtomValue(dateSortOrderAtom);
  const customQueryParameters = useActivityLogsQueryParameters();
  const { selectedTarget } = useSelectedTarget();
  const { selectedLog, close: closeLog } = useSelectedLog();
  useSyncSelectedTargetWithUrl();

  // The selected log is only kept in memory: leaving the page closes it
  useEffect(() => closeLog, []);

  const { elements, elementRef, hasNextPage, isLoading } =
    useInfiniteScrollListing<ActivityLog>({
      apiFormat: 'JSON-LD',
      customQueryParameters,
      decoder: activityLogsDecoder,
      endpoint: activityLogsEndpoint,
      limit: 30,
      pageAtom,
      parameters: { apiFormat: 'JSON-LD', sort: { date: dateSortOrder } },
      queryKeyName: 'activity-logs',
      suspense: false
    });

  return (
    <PageLayout className="relative">
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title
              description={t(labelLogsDescription)}
              descriptionPosition="inline"
              title={t(labelLogs)}
            />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Listing
          actions={
            <div className="grid w-full grid-cols-[minmax(max-content,1fr)_minmax(240px,480px)_1fr] items-center gap-4">
              <div className="justify-self-start">
                <PeriodFilter />
              </div>
              <Filters />
            </div>
          }
          elementRef={elementRef}
          hasNextPage={hasNextPage}
          isLoading={isLoading}
          pageAtom={pageAtom}
          rows={elements}
        />
      </PageLayout.Body>
      <SidePanelLayout
        log={selectedLog}
        rows={elements}
        target={selectedTarget}
      />
    </PageLayout>
  );
};

export default AdministrationLogsPage;
