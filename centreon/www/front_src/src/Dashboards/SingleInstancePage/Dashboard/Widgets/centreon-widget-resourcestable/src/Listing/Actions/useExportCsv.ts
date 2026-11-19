import { useFetchQuery, useSnackbar } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  defaultCheckedColumnAtom,
  defaultCheckedPageAtom
} from '../../../../../../../../Resources/Actions/exportToCsv/atoms';
import {
  ColumnId,
  PageId
} from '../../../../../../../../Resources/Actions/exportToCsv/models';
import { labelExportProcessingInProgress } from '../../../../../../../../Resources/translatedLabels';
import {
  buildCountEndpoint,
  buildExportEndpoint,
  exportResourcesEndpoint,
  maxExportedResources
} from '../../api/endpoints';
import { openTicketContextAtom } from '../../atom';
import type { ExportParameters } from '../models';

interface UseExportCsvProps {
  isOpen: boolean;
  parameters: ExportParameters;
}

interface UseExportCsvState {
  exportCsv: () => void;
  hasReachedMaximumLinesToExport: boolean;
  isLoading: boolean;
  numberExportedLines: number;
}

interface CountResponse {
  count: number;
}

const useExportCsv = ({
  isOpen,
  parameters
}: UseExportCsvProps): UseExportCsvState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const isAllColumnsChecked = equals(
    useAtomValue(defaultCheckedColumnAtom),
    ColumnId.allColumns
  );
  const isAllPagesChecked = equals(
    useAtomValue(defaultCheckedPageAtom),
    PageId.allPages
  );
  const {
    displayResources,
    isDownHostHidden,
    isOpenTicketEnabled,
    isUnreachableHostHidden,
    provider
  } = useAtomValue(openTicketContextAtom);

  const {
    columnIds,
    displayType,
    hostSeverities,
    limit,
    page,
    pageRowCount,
    resources,
    serviceSeverities,
    sortField,
    sortOrder,
    states,
    statuses,
    statusTypes,
    visibleColumnIds
  } = parameters;

  // The same filters, pagination and sort as the listing request
  // (`useLoadResources`), so that the file matches what the widget displays.
  const filters = {
    hostSeverities,
    resources,
    serviceSeverities,
    states,
    statuses,
    statusTypes,
    type: displayType,
    ...(isOpenTicketEnabled
      ? {
          displayResources,
          isDownHostHidden,
          isUnreachableHostHidden,
          provider
        }
      : {})
  };
  const pagination = {
    limit,
    page,
    sort: { [sortField as string]: sortOrder }
  };

  const countEndpoint = buildCountEndpoint(filters);

  const { data, isLoading: isCounting } = useFetchQuery<CountResponse>({
    getEndpoint: () => countEndpoint,
    getQueryKey: () => ['resourcestable', 'exportedLines', countEndpoint],
    queryOptions: {
      enabled: isOpen && isAllPagesChecked,
      gcTime: 0,
      staleTime: 0,
      suspense: false
    }
  });

  const isLoading = isAllPagesChecked && isCounting;
  const numberExportedLines = isAllPagesChecked
    ? data?.count || 0
    : pageRowCount;

  const exportCsv = (): void => {
    const exportUrl = new URL(
      buildExportEndpoint({
        ...filters,
        ...pagination,
        columns: isAllColumnsChecked ? columnIds : visibleColumnIds,
        isAllPages: isAllPagesChecked
      }),
      document.baseURI
    );

    if (
      !equals(exportUrl.origin, window.location.origin) ||
      !exportUrl.pathname.endsWith(`/api/latest${exportResourcesEndpoint}`)
    ) {
      return;
    }

    showSuccessMessage(t(labelExportProcessingInProgress));

    window.open(exportUrl.href, '_blank', 'noopener,noreferrer');
  };

  return {
    exportCsv,
    hasReachedMaximumLinesToExport: numberExportedLines > maxExportedResources,
    isLoading,
    numberExportedLines
  };
};

export default useExportCsv;
