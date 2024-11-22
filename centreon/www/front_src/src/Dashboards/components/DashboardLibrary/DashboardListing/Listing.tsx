import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { MemoizedListing, sanitizedHTML } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { List } from '../../../api/meta.models';
import { Dashboard } from '../../../api/models';
import {
  labelCancel,
  labelDelete,
  labelDeleteUser,
  labelYouAreGoingToDeleteUser
} from '../../../translatedLabels';

import { Actions } from './Actions';
import useColumns from './Columns/useColumns';
import { askBeforeRevokeAtom } from './atom';
import useListing from './useListing';

interface ListingProp {
  customListingComponent?: JSX.Element;
  data?: List<Dashboard>;
  displayCustomListing: boolean;
  loading: boolean;
  openConfig: () => void;
}

const Listing = ({
  data: listingData,
  loading,
  openConfig,
  customListingComponent,
  displayCustomListing
}: ListingProp): JSX.Element => {
  const { t } = useTranslation();
  const { columns, defaultColumnsIds } = useColumns();

  const askingBeforRevoke = useAtomValue(askBeforeRevokeAtom);

  const {
    changePage,
    changeSort,
    page,
    resetColumns,
    selectedColumnIds,
    setLimit,
    setSelectedColumnIds,
    sortf,
    sorto,
    getRowProperty,
    formattedRows,
    closeAskRevokeAccessRight,
    confirmRevokeAccessRight,
    navigateToDashboard
  } = useListing({ defaultColumnsIds, rows: listingData?.result });

  return (
    <>
      <MemoizedListing
        actions={<Actions openConfig={openConfig} />}
        columnConfiguration={{
          selectedColumnIds: displayCustomListing
            ? undefined
            : selectedColumnIds,
          sortable: true
        }}
        columns={columns}
        currentPage={(page || 1) - 1}
        customListingComponent={customListingComponent}
        displayCustomListing={displayCustomListing}
        limit={listingData?.meta.limit}
        loading={loading}
        memoProps={[columns, page, sorto, sortf]}
        rows={formattedRows}
        sortField={sortf}
        sortOrder={sorto}
        subItems={{
          canCheckSubItems: false,
          enable: true,
          getRowProperty,
          labelCollapse: 'Collapse',
          labelExpand: 'Expand'
        }}
        totalRows={listingData?.meta.total}
        onLimitChange={setLimit}
        onPaginate={changePage}
        onResetColumns={resetColumns}
        onRowClick={navigateToDashboard}
        onSelectColumns={setSelectedColumnIds}
        onSort={changeSort}
      />
      <Modal open={!!askingBeforRevoke} onClose={closeAskRevokeAccessRight}>
        <Modal.Header>{t(labelDeleteUser)}</Modal.Header>
        <Modal.Body>
          {sanitizedHTML({
            initialContent: t(labelYouAreGoingToDeleteUser, {
              name: askingBeforRevoke?.user.name
            })
          })}
        </Modal.Body>
        <Modal.Actions
          isDanger
          labels={{
            cancel: t(labelCancel),
            confirm: t(labelDelete)
          }}
          onCancel={closeAskRevokeAccessRight}
          onConfirm={
            askingBeforRevoke
              ? confirmRevokeAccessRight({
                  dashboardId: askingBeforRevoke?.dashboardId,
                  id: askingBeforRevoke?.user.id,
                  type: askingBeforRevoke?.user.type
                })
              : undefined
          }
        />
      </Modal>
    </>
  );
};

export default Listing;
