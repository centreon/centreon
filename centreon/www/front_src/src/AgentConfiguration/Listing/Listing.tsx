import { Listing } from '@centreon/ui';

import { AgentConfigurationListing } from '../models';

import Actions from './Actions/Actions';
import DeleteModal from './DeleteModal';

import { useTranslation } from 'react-i18next';
import { labelCollapse, labelExpand } from '../translatedLabels';
import { useColumns } from './Columns/useColumns';
import { useListing } from './useListing';

interface Props {
  rows: Array<AgentConfigurationListing>;
  total: number;
  isLoading: boolean;
}

const ACListing = ({ rows, total, isLoading }: Props): JSX.Element => {
  const { t } = useTranslation();

  const columns = useColumns();

  const {
    setPage,
    changeSort,
    page,
    limit,
    updateAgentConfiguration,
    resetColumns,
    selectColumns,
    selectedColumnIds,
    setLimit,
    sortField,
    sortOrder
  } = useListing();

  return (
    <div style={{ width: '100%', minHeight: '75vh' }}>
      <Listing
        actions={<Actions />}
        columns={columns}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
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
    </div>
  );
};

export default ACListing;
