import { useSnackbar } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';

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

const useListing = () => {
  const { showWarningMessage } = useSnackbar();

  const [page, setPage] = useAtom(pageAtom);
  const [limit, setLimit] = useAtom(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const changeSort = useSetAtom(changeSortAtom);
  const setOpenFormModal = useSetAtom(openFormModalAtom);
  const { isAdmin } = useAtomValue(userAtom);
  const { isCloudPlatform } = useAtomValue(platformFeaturesAtom);

  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length < 1) {
      showWarningMessage(t(labelSelectAtLeastOneColumn));

      return;
    }

    setSelectedColumnIds(updatedColumnIds);
  };

  const updateAgentConfiguration = ({
    id,
    internalListingParentId,
    pollers
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

  return {
    selectedColumnIds,
    selectColumns,
    resetColumns,
    updateAgentConfiguration,
    page,
    setPage,
    limit,
    setLimit,
    sortField,
    sortOrder,
    changeSort
  };
};

export default useListing;
