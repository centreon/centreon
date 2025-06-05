import { JSX } from 'react';

import { Listing } from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import { AgentConfigurationListing } from '../models';
import { labelCollapse, labelExpand } from '../translatedLabels';
import Actions from './Actions/Actions';

import { useColumns } from './Columns/useColumns';
import DeleteModal from './DeleteModal';
import useListing from './useListing';

interface Props {
  rows: Array<AgentConfigurationListing>;
  total: number;
  isLoading: boolean;
}

const ACListing = ({ rows, total, isLoading }: Props): JSX.Element => {
  const { t } = useTranslation();
  const columns = useColumns();

  const {
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
  } = useListing();

  return (
    <>
      <Listing
        actions={<Actions />}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
        columns={columns}
        subItems={{
          canCheckSubItems: false,
          enable: true,
          getRowProperty: () => 'pollers',
          labelExpand: t(labelExpand),
          labelCollapse: t(labelCollapse)
        }}
        loading={isLoading}
        onRowClick={updateAgentConfiguration}
        rows={rows}
        currentPage={page}
        onPaginate={setPage}
        limit={limit}
        onLimitChange={setLimit}
        totalRows={total}
        sortField={sortField}
        sortOrder={sortOrder}
        onSort={changeSort}
        onResetColumns={resetColumns}
        onSelectColumns={selectColumns}
      />
      <DeleteModal />
    </>
  );
};

export default ACListing;
