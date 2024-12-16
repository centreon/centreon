import { useEffect, useMemo, useState } from 'react';

import { useAtom } from 'jotai';
import { equals, isEmpty, isNotNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { type Column, useSnackbar } from '@centreon/ui';

import type { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { getResourcesUrl, goToUrl } from '../../../utils';
import {
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
import useIsOpenTicketInstalled from './useIsOpenTicketInstalled';
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
  data: ResourceListing | undefined;
  defaultSelectedColumnIds: Array<string>;
  goToResourceStatusPage?: (row) => void;
  hasMetaService: boolean;
  isLoading: boolean;
  onTicketClose: () => void;
  page: number | undefined;
  resetColumns: () => void;
  resourcesToAcknowledge;
  resourcesToOpenTicket: Array<Ticket>;
  resourcesToSetDowntime;
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
  displayResources: 'withTicket' | 'withoutTicket';
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden: boolean;
  isFromPreview?: boolean;
  isOpenTicketEnabled: boolean;
  isUnreachableHostHidden: boolean;
  limit?: number;
  provider?: { id: number; name: string };
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
}

const useListing = ({
  resources,
  isOpenTicketEnabled,
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
  isDownHostHidden,
  isUnreachableHostHidden,
  displayResources,
  provider
}: UseListingProps): UseListingState => {
  const { showWarningMessage } = useSnackbar();
  const { t } = useTranslation();

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

  useEffect(() => {
    if (isOpenTicketEnabled) {
      setPanelOptions?.({ displayType: DisplayType.Service });

      return;
    }

    setPanelOptions?.({ provider: {} });
  }, [isOpenTicketEnabled]);

  const isOpenTicketInstalled = useIsOpenTicketInstalled();

  const hasProvider = isNotNil(provider) && !isEmpty(provider);

  const { data, isLoading } = useLoadResources({
    dashboardId,
    displayResources,
    displayType,
    hostSeverities,
    id,
    isDownHostHidden,
    isUnreachableHostHidden,
    limit,
    page,
    playlistHash,
    provider,
    refreshCount,
    refreshIntervalToUse,
    resources,
    serviceSeverities,
    sortField,
    sortOrder,
    states,
    statusTypes,
    statuses,
    widgetPrefixQuery,
    isOpenTicketEnabled:
      isOpenTicketInstalled && hasProvider && isOpenTicketEnabled
  });

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

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const { columns, defaultSelectedColumnIds } = useColumns({
    displayResources,
    displayType,
    isOpenTicketEnabled,
    provider
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
    if (!hasMetaService) {
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
    goToResourceStatusPage,
    hasMetaService,
    isLoading,
    onTicketClose,
    page,
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
