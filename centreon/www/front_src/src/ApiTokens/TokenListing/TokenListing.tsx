import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import Divider from '@mui/material/Divider';

import { MemoizedListing as Listing } from '@centreon/ui';

import TokenCreationButton from '../TokenCreation';
import { labelApiToken } from '../translatedLabels';

import Actions from './Actions';

import { useColumns } from './ComponentsColumn/useColumns';
import Title from './Title';
import { selectedRowAtom } from './atoms';
import { useStyles } from './tokenListing.styles';
import useListing from './useListing';
import useLoadData from './useLoadData';

const TokenListing = (): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const setSelectRow = useSetAtom(selectedRowAtom);

  const { data, isLoading } = useLoadData();

  const { changePage, changeSort, page, setLimit, sortf, sorto } = useListing();

  const { columns, selectedColumnIds, onSelectColumns, onResetColumns } =
    useColumns();

  const selectRow = (row): void => {
    setSelectRow(row);
  };

  return (
    <div className={classes.container}>
      <Title msg={t(labelApiToken)} />
      <Divider className={classes.divider} />
      <Listing
        innerScrollDisabled
        actions={<Actions buttonCreateToken={<TokenCreationButton />} />}
        columnConfiguration={{ selectedColumnIds, sortable: true }}
        columns={columns}
        currentPage={(page || 1) - 1}
        getId={({ name, user }) => `${name}-${user.id}`}
        limit={data?.meta.limit}
        loading={isLoading}
        rows={data?.result}
        sortField={sortf}
        sortOrder={sorto}
        totalRows={data?.meta.total}
        onLimitChange={setLimit}
        onPaginate={changePage}
        onResetColumns={onResetColumns}
        onRowClick={selectRow}
        onSelectColumns={onSelectColumns}
        onSort={changeSort}
        memoProps={[columns, page, sorto, sortf]}
      />
    </div>
  );
};
export default TokenListing;
