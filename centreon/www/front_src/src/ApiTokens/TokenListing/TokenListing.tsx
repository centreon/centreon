import { useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { useTranslation } from 'react-i18next';

import Divider from '@mui/material/Divider';

import { MemoizedListing as Listing, useResizeObserver } from '@centreon/ui';

import TokenCreationButton from '../TokenCreation';
import { labelApiToken } from '../translatedLabels';

import Actions from './Actions';
import Refresh from './Actions/Refresh';
import { useColumns } from './ComponentsColumn/useColumns';
import Title from './Title';
import { selectedRowAtom } from './atoms';
import { useStyles } from './tokenListing.styles';
import { useTokenListing } from './useTokenListing';

interface Props {
  id: string;
}

const TokenListing = ({ id = 'root' }: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const setSelectRow = useSetAtom(selectedRowAtom);

  const { width } = useResizeObserver<HTMLElement>({
    ref: document.getElementById(id)
  });

  const {
    dataListing,
    changePage,
    changeLimit,
    onSort,
    sortedField,
    sortOrder,
    refetch
  } = useTokenListing({});

  const { columns, selectedColumnIds, onSelectColumns, onResetColumns } =
    useColumns();

  const selectRow = (row): void => {
    setSelectRow(row);
  };

  return (
    <div className={classes.container}>
      <Title msg={pluralize(t(labelApiToken))} />
      <Divider className={classes.divider} />
      <Listing
        innerScrollDisabled
        actions={
          <Actions
            buttonCreateToken={<TokenCreationButton />}
            refresh={
              <Refresh isLoading={dataListing?.isLoading} onRefresh={refetch} />
            }
            width={width}
          />
        }
        actionsBarMemoProps={[dataListing?.isLoading, width]}
        columnConfiguration={{ selectedColumnIds, sortable: true }}
        columns={columns}
        currentPage={(dataListing?.page || 1) - 1}
        getId={({ name, user }) => `${name}-${user.id}`}
        limit={dataListing?.limit}
        loading={dataListing?.isLoading}
        memoProps={[width]}
        rows={dataListing?.rows}
        sortField={sortedField}
        sortOrder={sortOrder}
        totalRows={dataListing?.total}
        onLimitChange={changeLimit}
        onPaginate={changePage}
        onResetColumns={onResetColumns}
        onRowClick={selectRow}
        onSelectColumns={onSelectColumns}
        onSort={onSort}
      />
    </div>
  );
};
export default TokenListing;
