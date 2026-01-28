import { Listing } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { AgentConfigurationListing } from '../models';
import { labelCollapse, labelExpand } from '../translatedLabels';
import Actions from './Actions/Actions';
import { useColumns } from './Columns/useColumns';
import DeleteModal from './DeleteModal';
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
    <>
      <Listing
        actions={<Actions />}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
        columns={columns}
        currentPage={page}
        limit={limit}
        loading={isLoading}
        onLimitChange={setLimit}
        onPaginate={setPage}
        onResetColumns={resetColumns}
        onRowClick={updateAgentConfiguration}
        onSelectColumns={selectColumns}
        onSort={changeSort}
        rows={rows}
        sortField={sortField}
        sortOrder={sortOrder}
        subItems={{
          canCheckSubItems: false,
          enable: true,
          getRowProperty: () => 'pollers',
          labelCollapse: t(labelCollapse),
          labelExpand: t(labelExpand)
        }}
        totalRows={total}
      />
      <DeleteModal />
    </>
  );
};

export default ACListing;
