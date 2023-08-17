import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals, includes, isEmpty, isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { alpha, useTheme } from '@mui/material';

import {
  MemoizedListing as Listing,
  Method,
  SeverityCode,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { userEndpoint } from '../../App/endpoint';
import Actions from '../Actions';
import {
  resourcesToAcknowledgeAtom,
  resourcesToSetDowntimeAtom,
  selectedResourcesAtom
} from '../Actions/actionsAtoms';
import { forcedCheckInlineEndpointAtom } from '../Actions/Resource/Check/checkAtoms';
import { adjustCheckedResources } from '../Actions/Resource/Check/helpers';
import { rowColorConditions } from '../colors';
import {
  openDetailsTabIdAtom,
  panelWidthStorageAtom,
  selectedResourcesDetailsAtom,
  selectedResourceUuidAtom
} from '../Details/detailsAtoms';
import { graphTabId } from '../Details/tabs';
import {
  getCriteriaValueDerivedAtom,
  searchAtom,
  setCriteriaAndNewFilterDerivedAtom
} from '../Filter/filterAtoms';
import { Resource, SortOrder } from '../models';
import {
  labelSelectAtLeastOneColumn,
  labelStatus,
  labelForcedCheckCommandSent
} from '../translatedLabels';

import { defaultSelectedColumnIds, getColumns } from './columns';
import {
  enabledAutorefreshAtom,
  limitAtom,
  listingAtom,
  pageAtom,
  selectedColumnIdsAtom,
  sendingAtom
} from './listingAtoms';
import useLoadResources from './useLoadResources';
import useViewerMode from './useViewerMode';

export const okStatuses = ['OK', 'UP'];

const ResourceListing = (): JSX.Element => {
  const theme = useTheme();
  const { t } = useTranslation();
  const { isPending, updateUser, viewerMode } = useViewerMode();
  const { showWarningMessage, showSuccessMessage } = useSnackbar();

  const [selectedResourceUuid, setSelectedResourceUuid] = useAtom(
    selectedResourceUuidAtom
  );
  const [page, setPage] = useAtom(pageAtom);
  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );
  const [selectedResources, setSelectedResources] = useAtom(
    selectedResourcesAtom
  );
  const [selectedResourceDetails, setSelectedResourceDetails] = useAtom(
    selectedResourcesDetailsAtom
  );
  const { user_interface_density, themeMode } = useAtomValue(userAtom);
  const listing = useAtomValue(listingAtom);
  const sending = useAtomValue(sendingAtom);
  const enabledAutoRefresh = useAtomValue(enabledAutorefreshAtom);
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);
  const search = useAtomValue(searchAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const forcedCheckInlineEndpoint = useAtomValue(forcedCheckInlineEndpointAtom);
  const setOpenDetailsTabId = useSetAtom(openDetailsTabIdAtom);
  const setLimit = useSetAtom(limitAtom);
  const setResourcesToAcknowledge = useSetAtom(resourcesToAcknowledgeAtom);
  const setResourcesToSetDowntime = useSetAtom(resourcesToSetDowntimeAtom);
  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const { initAutorefreshAndLoad } = useLoadResources();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => userEndpoint,
    method: Method.PATCH
  });
  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => forcedCheckInlineEndpoint,
    method: Method.POST
  });

  const isPanelOpen = !isNil(selectedResourceDetails?.resourceId);

  const changeSort = ({ sortField, sortOrder }): void => {
    setCriteriaAndNewFilter({
      apply: true,
      name: 'sort',
      value: [sortField, sortOrder]
    });
  };

  const changeLimit = (value): void => {
    setLimit(Number(value));
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const selectResource = ({ id, links, uuid }: Resource): void => {
    setSelectedResourceUuid(uuid);
    setSelectedResourceDetails({
      resourceId: id,
      resourcesDetailsEndpoint: links?.endpoints?.details
    });
  };

  const resourceDetailsOpenCondition = {
    color: alpha(theme.palette.primary.main, 0.12),
    condition: ({ id }): boolean => {
      if (isEmpty(selectedResourceDetails) || isNil(selectedResourceDetails)) {
        return false;
      }

      const { parentResourceId } = selectedResourceDetails;

      return parentResourceId
        ? equals(id, parentResourceId)
        : equals(id, selectedResourceDetails?.resourceId);
    },
    name: 'detailsOpen'
  };

  const onForcedCheck = (resource: Resource): void => {
    checkResource({
      check: { is_forced: true },
      resources: adjustCheckedResources({ resources: [resource] })
    }).then(() => {
      showSuccessMessage(t(labelForcedCheckCommandSent));
    });
  };

  const columns = getColumns({
    actions: {
      onAcknowledge: (resource): void => {
        setResourcesToAcknowledge([resource]);
      },
      onCheck: (resource): void => {
        onForcedCheck(resource);
      },
      onDisplayGraph: (resource): void => {
        setOpenDetailsTabId(graphTabId);

        selectResource(resource);
      },
      onDowntime: (resource): void => {
        setResourcesToSetDowntime([resource]);
      }
    },
    t
  });

  const loading = sending;

  const [sortField, sortOrder] = getCriteriaValue('sort') as [
    string,
    SortOrder
  ];

  const getId = ({ uuid }: Resource): string => uuid;

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length === 0) {
      showWarningMessage(t(labelSelectAtLeastOneColumn));

      return;
    }

    setSelectedColumnIds(updatedColumnIds);
  };

  const predefinedRowsSelection = [
    {
      label: `${t(labelStatus).toLowerCase()}:OK`,
      rowCondition: ({ status }): boolean => includes(status.name, okStatuses)
    },
    {
      label: `${t(labelStatus).toLowerCase()}:NOK`,
      rowCondition: ({ status }): boolean =>
        not(includes(status.name, okStatuses))
    }
  ];

  const changeViewModeTableResources = (): void => {
    updateUser();
    mutateAsync({
      user_interface_density: viewerMode
    });
  };

  return (
    <Listing
      checkable
      actions={<Actions onRefresh={initAutorefreshAndLoad} />}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      getHighlightRowCondition={({ status }): boolean =>
        equals(status?.severity_code, SeverityCode.High)
      }
      getId={getId}
      headerMemoProps={[search]}
      limit={listing?.meta.limit}
      loading={loading}
      memoProps={[
        listing,
        sortField,
        sortOrder,
        page,
        selectedResources,
        selectedResourceUuid,
        sending,
        enabledAutoRefresh,
        selectedResourceDetails,
        themeMode
      ]}
      moveTablePagination={isPanelOpen}
      predefinedRowsSelection={predefinedRowsSelection}
      rowColorConditions={[
        resourceDetailsOpenCondition,
        ...rowColorConditions(theme)
      ]}
      rows={listing?.result}
      selectedRows={selectedResources}
      sortField={sortField}
      sortOrder={sortOrder}
      totalRows={listing?.meta.total}
      viewMode={user_interface_density}
      viewerModeConfiguration={{
        disabled: isPending,
        onClick: changeViewModeTableResources,
        title: user_interface_density
      }}
      widthToMoveTablePagination={panelWidth}
      onLimitChange={changeLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={selectResource}
      onSelectColumns={selectColumns}
      onSelectRows={setSelectedResources}
      onSort={changeSort}
    />
  );
};

export default ResourceListing;
