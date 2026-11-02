import { useInfiniteScrollListing } from '@centreon/ui';
import { PageHeader, PageLayout } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { activityLogsDecoder } from './api/decoders';
import { activityLogsEndpoint } from './api/endpoints';
import { dateSortOrderAtom, pageAtom } from './atoms';
import Filters from './Filters/Filters';
import PeriodFilter from './Filters/PeriodFilter';
import HistoryPanelLayout from './History/HistoryPanelLayout';
import Listing from './Listing/Listing';
import type { ActivityLog } from './models';
import { labelLogs } from './translatedLabels';
import { useActivityLogsQueryParameters } from './useActivityLogsQueryParameters';
import {
  useSelectedTarget,
  useSyncSelectedTargetWithUrl
} from './useSelectedTarget';

const AdministrationLogsPage = (): JSX.Element => {
  const { t } = useTranslation();

  const dateSortOrder = useAtomValue(dateSortOrderAtom);
  const customQueryParameters = useActivityLogsQueryParameters();
  const { selectedTarget } = useSelectedTarget();
  useSyncSelectedTargetWithUrl();

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
            <PageHeader.Title title={t(labelLogs)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Listing
          actions={
            <div className="grid w-full grid-cols-[1fr_minmax(240px,480px)_1fr] items-center gap-4">
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
      <HistoryPanelLayout target={selectedTarget} />
    </PageLayout>
  );
};

export default AdministrationLogsPage;
