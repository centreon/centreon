import { type Column, useFetchQuery, useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useEffect, useMemo, useState } from 'react';
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
  type Resource as ListingResource,
  type NamedEntity,
  type ResourceListing,
  type Ticket
} from './models';
import { labelSelectAtLeastThreeColumns } from './translatedLabels';
import useLoadResources from './useLoadResources';

interface CountResponse {
  count: number;
  is_approximate: boolean;
}

interface UseListingState {
  cancelAcknowledge: () => void;
  cancelSetDowntime: () => void;
  changeLimit: (value: number) => void;
  changePage: (updatedPage: number) => void;
  changeSort: ({
    sortOrder,
    sortField
  }: {
    sortField: string;
    sortOrder: SortOrder;
  }) => void;
  columns: Array<Column>;
  confirmAcknowledge: () => void;
  confirmSetDowntime: () => void;
  data: ResourceListing | undefined;
  defaultSelectedColumnIds: Array<string>;
  exactCount: number | null;
  goToResourceStatusPage?: (row: ListingResource) => void;
  hasMetaService: boolean;
  isExactCountLoading: boolean;
  isLoading: boolean;
  onTicketClose: () => void;
  page: number | undefined;
  requestExactCount: () => void;
  resetColumns: () => void;
  resourcesToAcknowledge: Array<ListingResource>;
  resourcesToOpenTicket: Array<Ticket>;
  resourcesToSetDowntime: Array<ListingResource>;
  selectColumns: (updatedColumnIds: Array<string>) => void;
  selectedResources: Array<ListingResource>;
  setSelectedResources: (resources: Array<ListingResource>) => void;
}

interface UseListingProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  changeViewMode?: (displayType: DisplayType) => void;
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
  const {
    displayResources,
    isDownHostHidden,
    isOpenTicketEnabled,
    isUnreachableHostHidden,
    provider
  } = useAtomValue(openTicketContextAtom);

  const [page, setPage] = useState(1);
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

  const [exactCount, setExactCount] = useState<number | null>(null);

  const getCountEndpoint = (): string =>
    buildCountEndpoint({
      displayResources: isOpenTicketEnabled ? displayResources : undefined,
      hostSeverities,
      isDownHostHidden: isOpenTicketEnabled ? isDownHostHidden : undefined,
      isUnreachableHostHidden: isOpenTicketEnabled
        ? isUnreachableHostHidden
        : undefined,
      provider: isOpenTicketEnabled ? provider : undefined,
      resources,
      serviceSeverities,
      states,
      statuses,
      statusTypes,
      type: displayType
    });

  const {
    data: countData,
    isFetching: isExactCountLoading,
    refetch
  } = useFetchQuery<CountResponse>({
    getEndpoint: getCountEndpoint,
    getQueryKey: () => ['exactCount', getCountEndpoint()],
    queryOptions: {
      enabled: false,
      suspense: false
    }
  });

  useEffect(() => {
    if (countData?.count !== undefined) {
      setExactCount(countData.count);
    }
  }, [countData]);

  useEffect(() => {
    setExactCount(null);
  }, [
    displayType,
    JSON.stringify(resources),
    JSON.stringify(states),
    JSON.stringify(statuses),
    JSON.stringify(statusTypes),
    JSON.stringify(hostSeverities),
    JSON.stringify(serviceSeverities)
  ]);

  const requestExactCount = (): void => {
    refetch();
  };

  useEffect(() => {
    if (isOpenTicketEnabled && isFromPreview) {
      setPanelOptions?.({ displayType: DisplayType.Service });

      return;
    }
  }, [isOpenTicketEnabled]);

  const { data, isLoading } = useLoadResources({
    dashboardId,
    displayType,
    hostSeverities,
    id,
    isInViewport,
    limit,
    page,
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

  const goToResourceStatusPage = (row: ListingResource): void => {
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

  const changeSort = (sortParameters: {
    sortField: string;
    sortOrder: SortOrder;
  }): void => {
    setPanelOptions?.(sortParameters);
  };

  const changeLimit = (value: number): void => {
    setPanelOptions?.({ limit: value });
  };

  const changePage = (updatedPage: number): void => {
    setPage(updatedPage + 1);
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
    data,
    defaultSelectedColumnIds,
    exactCount,
    goToResourceStatusPage,
    hasMetaService,
    isExactCountLoading,
    isLoading,
    onTicketClose,
    page,
    requestExactCount,
    resetColumns,
    resourcesToAcknowledge,
    resourcesToOpenTicket,
    resourcesToSetDowntime,
    selectColumns,
    selectedResources,
    setSelectedResources
  };
};

export default useListing;
