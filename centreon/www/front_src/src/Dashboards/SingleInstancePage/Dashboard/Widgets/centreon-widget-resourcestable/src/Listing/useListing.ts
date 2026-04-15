import { type Column, useFetchQuery, useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { getResourcesUrl, goToUrl } from '../../../utils';
import { buildCountEndpoint } from '../api/endpoints';
import {
  openTicketContextAtom,
  resourcesToAcknowledgeAtom,
  resourcesToOpenTicketAtom,
  resourcesToSetDowntimeAtom,
  selectedResourcesAtom
} from '../atom';
import type { PanelOptions } from '../models';
import useColumns from './Columns/useColumns';
import {
  DisplayType,
  type NamedEntity,
  type ResourceListing,
  type Ticket
} from './models';
import { labelSelectAtLeastThreeColumns } from './translatedLabels';
import useLoadResources from './useLoadResources';

interface UseListingState {
  cancelAcknowledge: () => void;
  cancelSetDowntime: () => void;
  changeLimit: (value) => void;
  changePage: (updatedPage) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  columns: Array<Column>;
  confirmAcknowledge: () => void;
  confirmSetDowntime: () => void;
  currentCursorIndex: number;
  cursorStack: Array<string | null>;
  data: ResourceListing | undefined;
  defaultSelectedColumnIds: Array<string>;
  goToResourceStatusPage?: (row) => void;
  hasMetaService: boolean;
  isCountLoading: boolean;
  isLoading: boolean;
  onTicketClose: () => void;
  resetColumns: () => void;
  resourcesToAcknowledge;
  resourcesToOpenTicket: Array<Ticket>;
  resourcesToSetDowntime;
  resourceCount: number | undefined;
  selectColumns: (updatedColumnIds: Array<string>) => void;
  selectedResources;
  setSelectedResources;
}

interface UseListingProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  changeViewMode?: (displayType) => void;
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  isFromPreview?: boolean;
  limit?: number;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  serviceSeverities: Array<NamedEntity>;
  setPanelOptions?: (partialOptions: object) => void;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statusTypes: Array<'hard' | 'soft'>;
  statuses: Array<string>;
  isInViewport: boolean;
}

const useListing = ({
  resources,
  states,
  statuses,
  displayType,
  refreshCount,
  refreshIntervalToUse,
  setPanelOptions,
  limit,
  sortField,
  sortOrder,
  changeViewMode,
  isFromPreview,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery,
  statusTypes,
  hostSeverities,
  serviceSeverities,
  isInViewport
}: UseListingProps): UseListingState => {
  const { showWarningMessage } = useSnackbar();
  const { t } = useTranslation();
  const { isOpenTicketEnabled, isDownHostHidden, isUnreachableHostHidden } =
    useAtomValue(openTicketContextAtom);

  const [cursorStack, setCursorStack] = useState<Array<string | null>>([null]);
  const [currentCursorIndex, setCurrentCursorIndex] = useState(0);
  const currentCursorIndexRef = useRef(currentCursorIndex);
  const [resourcesToOpenTicket, setResourcesToOpenTicket] = useAtom(
    resourcesToOpenTicketAtom
  );

  const [selectedResources, setSelectedResources] = useAtom(
    selectedResourcesAtom
  );

  const [resourcesToAcknowledge, setResourcesToAcknowledge] = useAtom(
    resourcesToAcknowledgeAtom
  );
  const [resourcesToSetDowntime, setResourcesToSetDowntime] = useAtom(
    resourcesToSetDowntimeAtom
  );

  useEffect(() => {
    if (isOpenTicketEnabled && isFromPreview) {
      setPanelOptions?.({ displayType: DisplayType.Service });

      return;
    }
  }, [isOpenTicketEnabled]);

  useEffect(() => {
    currentCursorIndexRef.current = currentCursorIndex;
  }, [currentCursorIndex]);

  // Key derived from all filter inputs — changes when any filter/sort/limit changes.
  const filterKey = useMemo(
    () =>
      JSON.stringify({
        displayType,
        hostSeverities,
        limit,
        resources,
        serviceSeverities,
        sortField,
        sortOrder,
        states,
        statuses,
        statusTypes
      }),
    [
      displayType,
      hostSeverities,
      limit,
      resources,
      serviceSeverities,
      sortField,
      sortOrder,
      states,
      statuses,
      statusTypes
    ]
  );

  useEffect(() => {
    setCursorStack([null]);
    setCurrentCursorIndex(0);
  }, [filterKey]);

  const countEndpoint = buildCountEndpoint({
    hostSeverities,
    isDownHostHidden,
    isUnreachableHostHidden,
    resources,
    serviceSeverities,
    states,
    statuses,
    statusTypes,
    type: displayType
  });

  const { data: countData, isLoading: isCountLoading } = useFetchQuery<{
    count: number;
  }>({
    getEndpoint: () => countEndpoint,
    getQueryKey: () => ['resourcesTableCount', countEndpoint, refreshCount],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  const resourceCount = countData?.count;

  const { data, isLoading } = useLoadResources({
    cursor: cursorStack[currentCursorIndex] ?? null,
    dashboardId,
    displayType,
    hostSeverities,
    id,
    isInViewport,
    limit,
    playlistHash,
    refreshCount,
    refreshIntervalToUse,
    resources,
    serviceSeverities,
    sortField,
    sortOrder,
    states,
    statuses,
    statusTypes,
    widgetPrefixQuery
  });

  useEffect(() => {
    const nextCursor = data?.meta?.next_cursor;
    if (nextCursor === undefined) {
      return;
    }
    if (nextCursor !== null) {
      setCursorStack((prev) => {
        if (prev.length <= currentCursorIndexRef.current + 1) {
          return [...prev, nextCursor];
        }

        return prev;
      });
    }
  }, [data]);

  const goToResourceStatusPage = (row): void => {
    if (isFromPreview) {
      return;
    }

    const linkToResourceStatus = getResourcesUrl({
      allResources: resources,
      isForOneResource: true,
      resource: { ...row, parentId: row?.parent?.id },
      states,
      statuses,
      type: displayType
    });

    changeViewMode?.(displayType);
    goToUrl(linkToResourceStatus)();
  };

  const hasMetaService = useMemo(
    () =>
      resources.some(({ resourceType }) =>
        equals(resourceType, 'meta-service')
      ),
    [resources]
  );

  const changeSort = (sortParameters): void => {
    setPanelOptions?.(sortParameters);
  };

  const changeLimit = (value): void => {
    setPanelOptions?.({ limit: value });
  };

  const changePage = (updatedPage: number): void => {
    setCurrentCursorIndex(updatedPage);
  };

  const { columns, defaultSelectedColumnIds } = useColumns({
    displayType
  });

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length < 3) {
      showWarningMessage(t(labelSelectAtLeastThreeColumns));

      return;
    }

    setPanelOptions?.({ selectedColumnIds: updatedColumnIds });
  };

  const resetColumns = (): void => {
    setPanelOptions?.({ selectedColumnIds: defaultSelectedColumnIds });
  };

  useEffect(() => {
    if (!hasMetaService || !isFromPreview) {
      return;
    }

    setPanelOptions?.({ displayType: DisplayType.Service });
  }, [hasMetaService]);

  const cancelAcknowledge = (): void => {
    setResourcesToAcknowledge([]);
  };

  const cancelSetDowntime = (): void => {
    setResourcesToSetDowntime([]);
  };

  const confirmSetDowntime = (): void => {
    setResourcesToSetDowntime([]);

    setSelectedResources([]);
  };

  const confirmAcknowledge = (): void => {
    setResourcesToAcknowledge([]);

    setSelectedResources([]);
  };

  const onTicketClose = (): void => {
    setResourcesToOpenTicket([]);
  };

  return {
    cancelAcknowledge,
    cancelSetDowntime,
    changeLimit,
    changePage,
    changeSort,
    columns,
    confirmAcknowledge,
    confirmSetDowntime,
    currentCursorIndex,
    cursorStack,
    data,
    defaultSelectedColumnIds,
    goToResourceStatusPage,
    hasMetaService,
    isCountLoading,
    isLoading,
    onTicketClose,
    resetColumns,
    resourceCount,
    resourcesToAcknowledge,
    resourcesToOpenTicket,
    resourcesToSetDowntime,
    selectColumns,
    selectedResources,
    setSelectedResources
  };
};

export default useListing;
