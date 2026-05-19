import { useSnackbar } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  changeSortAtom,
  limitAtom,
  openFormModalAtom,
  pageAtom,
  selectedColumnIdsAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import { labelSelectAtLeastOneColumn } from '../translatedLabels';
import { defaultSelectedColumnIds } from '../utils';

interface UseListing {
  setPage: (updatedPage: number) => void;
  changeSort: ({
    sortOrder,
    sortField
  }: {
    sortOrder: string;
    sortField: string;
  }) => void;
  page?: number;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
  setLimit: (limit: number) => void;
  sortField: string;
  sortOrder: string;
  limit: number;
  updateAgentConfiguration: ({
    id,
    internalListingParentId,
    pollers
  }: {
    id: number;
    internalListingParentId?: number;
    pollers: Array<{ isCentral?: boolean }>;
  }) => void;
}

export const useListing = (): UseListing => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const [page, setPage] = useAtom(pageAtom);
  const [limit, setLimit] = useAtom(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const changeSort = useSetAtom(changeSortAtom);
  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const { isAdmin } = useAtomValue(userAtom);
  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const updateAgentConfiguration = ({
    id,
    internalListingParentId,
    pollers
  }: {
    id: number;
    internalListingParentId?: number;
    pollers: Array<{ isCentral?: boolean }>;
  }) => {
    const hasCentral = pollers.some((poller) =>
      equals(poller?.isCentral, true)
    );

    if (
      isNotNil(internalListingParentId) ||
      (!isAdmin && isCloudPlatform && hasCentral)
    ) {
      return;
    }

    setOpenFormModal(id);
  };

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length < 1) {
      showWarningMessage(t(labelSelectAtLeastOneColumn));

      return;
    }

    setSelectedColumnIds(updatedColumnIds);
  };

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  return {
    changeSort,
    limit,
    page,
    resetColumns,
    selectColumns,
    selectedColumnIds,
    setLimit,
    setPage,
    sortField,
    sortOrder,
    updateAgentConfiguration
  };
};
