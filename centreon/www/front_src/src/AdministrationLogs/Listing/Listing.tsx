import { alpha, CircularProgress, Typography, useTheme } from '@mui/material';

import { Listing as ListingComponent } from '@centreon/ui';

import { type PrimitiveAtom, useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { dateSortOrderAtom } from '../atoms';
import ChangesTable from '../Changes/ChangesTable';
import { Action, type ActivityLog } from '../models';
import { labelEndOfHistory, labelNoResultFound } from '../translatedLabels';
import { useExpandedLogs } from '../useExpandedLogs';
import { useSelectedTarget } from '../useSelectedTarget';
import DayHeader, { useDayKey } from './DayHeader';
import { useColumns } from './useColumns';

// Left accent of the expanded changes, in the color of the action chip
const actionBorderClassNames: Record<Action, string> = {
  [Action.Add]: 'border-l-success-main',
  [Action.Delete]: 'border-l-error-main',
  [Action.Disable]: 'border-l-error-main',
  [Action.Enable]: 'border-l-success-main',
  [Action.MassChange]: 'border-l-warning-main',
  [Action.Update]: 'border-l-warning-main'
};

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
  const { isSelected } = useSelectedTarget();
  const getDayKey = useDayKey();
  const { isExpanded, toggle } = useExpandedLogs();

  // Shared by the expanded row and its changes, so they read as one block
  const expandedBackgroundColor = alpha(theme.palette.primary.main, 0.04);

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
      expandableRow={{
        isExpanded: ({ id }) => isExpanded(id),
        render: ({ action, id }) => (
          <div
            className={`border-0 border-b border-l-4 border-solid border-b-divider py-3 pr-4 pl-11 ${actionBorderClassNames[action]}`}
            style={{ backgroundColor: expandedBackgroundColor }}
          >
            <ChangesTable logId={id} />
          </div>
        )
      }}
      groupHeader={{
        getKey: ({ date }) => getDayKey(date),
        render: ({ date }) => <DayHeader date={date} />
      }}
      labelNoResultFound={t(labelNoResultFound)}
      limit={30}
      loading={isLoading}
      onRowClick={({ id, hasChanges }) => hasChanges && toggle(id)}
      onSort={sortByDate}
      paginated={false}
      rowColorConditions={[
        {
          color: expandedBackgroundColor,
          condition: (row) => isExpanded((row as unknown as ActivityLog).id),
          name: 'expanded'
        },
        {
          // The rows of the object whose history is open in the side panel
          color: alpha(theme.palette.primary.main, 0.1),
          condition: (row) =>
            isSelected((row as unknown as ActivityLog).target),
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
