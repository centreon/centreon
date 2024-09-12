import { Listing } from '@centreon/ui';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import {
  changeSortAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atoms';
import { AgentConfigurationListing } from '../models';
import { labelCollapse, labelExpand } from '../translatedLabels';
import Actions from './Actions/Actions';
import { useColumns } from './Columns/useColumns';
import DeleteModal from './DeleteModal';

interface Props {
  rows: Array<AgentConfigurationListing>;
  total: number;
  isLoading: boolean;
}

const ACListing = ({ rows, total, isLoading }: Props): JSX.Element => {
  const { t } = useTranslation();
  const columns = useColumns();

  const [page, setPage] = useAtom(pageAtom);
  const [limit, setLimit] = useAtom(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const changeSort = useSetAtom(changeSortAtom);

  return (
    <>
      <Listing
        actions={<Actions />}
        columns={columns}
        subItems={{
          canCheckSubItems: false,
          enable: true,
          getRowProperty: () => 'pollers',
          labelExpand: t(labelExpand),
          labelCollapse: t(labelCollapse)
        }}
        loading={isLoading}
        rows={rows}
        currentPage={page}
        onPaginate={setPage}
        limit={limit}
        onLimitChange={setLimit}
        totalRows={total}
        sortField={sortField}
        sortOrder={sortOrder}
        onSort={changeSort}
      />
      <DeleteModal />
    </>
  );
};

export default ACListing;
