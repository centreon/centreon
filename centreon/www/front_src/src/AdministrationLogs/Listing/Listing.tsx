import { alpha, CircularProgress, Typography, useTheme } from '@mui/material';

import { Listing as ListingComponent } from '@centreon/ui';

import { type PrimitiveAtom, useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { dateSortOrderAtom } from '../atoms';
import type { ActivityLog } from '../models';
import { labelEndOfHistory, labelNoResultFound } from '../translatedLabels';
import { useSelectedLog } from '../useSelectedLog';
import { useSelectedTarget } from '../useSelectedTarget';
import DayHeader, { useDayKey } from './DayHeader';
import { useColumns } from './useColumns';

interface Props {
  actions?: JSX.Element;
  elementRef: (node: Element | null) => void;
  isLoading: boolean;
  pageAtom: PrimitiveAtom<number>;
  rows: Array<ActivityLog>;
  hasNextPage: boolean;
}

const Listing = ({
  actions,
  elementRef,
  isLoading,
  pageAtom,
  rows,
  hasNextPage
}: Props): JSX.Element => {
  const [dateSortOrder, setDateSortOrder] = useAtom(dateSortOrderAtom);
  const setPage = useSetAtom(pageAtom);

  const sortByDate = ({ sortOrder }: { sortOrder: 'asc' | 'desc' }): void => {
    setPage(1);
    setDateSortOrder(sortOrder);
  };

  const { t } = useTranslation();

  const theme = useTheme();

  const columns = useColumns();
  const { isSelected: isSelectedTarget } = useSelectedTarget();
  const { isSelected: isSelectedLog, select: selectLog } = useSelectedLog();
  const getDayKey = useDayKey();

  return (
    <ListingComponent<ActivityLog>
      actions={actions}
      bodyFooter={
        <div
          className="flex h-10 items-center justify-center"
          ref={hasNextPage ? elementRef : undefined}
        >
          {hasNextPage ? (
            <CircularProgress size={20} />
          ) : (
            <Typography className="text-text-secondary" variant="caption">
              {t(labelEndOfHistory)}
            </Typography>
          )}
        </div>
      }
      columns={columns}
      groupHeader={{
        getKey: ({ date }) => getDayKey(date),
        render: ({ date }) => <DayHeader date={date} />
      }}
      labelNoResultFound={t(labelNoResultFound)}
      limit={30}
      loading={isLoading}
      onRowClick={selectLog}
      onSort={sortByDate}
      paginated={false}
      rowColorConditions={[
        {
          // The log open in the side panel. The first matching condition wins
          color: alpha(theme.palette.primary.main, 0.16),
          condition: (row) => isSelectedLog((row as unknown as ActivityLog).id),
          name: 'selected-log'
        },
        {
          // The rows of the object whose history is open in the side panel
          color: alpha(theme.palette.primary.main, 0.08),
          condition: (row) =>
            isSelectedTarget((row as unknown as ActivityLog).target),
          name: 'selected-object'
        }
      ]}
      rows={rows}
      sortField="date"
      sortOrder={dateSortOrder}
      totalRows={rows.length}
    />
  );
};

export default Listing;
