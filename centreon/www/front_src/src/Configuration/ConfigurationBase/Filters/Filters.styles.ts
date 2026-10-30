import { makeStyles } from 'tss-react/mui';

import type { FiltersPanelWidth } from '../../models';

export const defaultFiltersPanelWidth = 42;

interface FilterStylesParams {
  filtersPanelWidth?: FiltersPanelWidth;
}

export const useFilterStyles = makeStyles<FilterStylesParams>()(
  (theme, { filtersPanelWidth }) => ({
    additionalFilters: {
      background: theme.palette.background.paper,
      display: 'flex',
      flexDirection: 'column',
      gap: theme.spacing(1.5),
      // An icon-anchored panel clears the field with this margin; a `full`
      // one already hangs from the field's bottom edge and stays flush to it.
      marginTop: filtersPanelWidth === 'full' ? 0 : theme.spacing(1),
      padding: theme.spacing(2),
      // `full`: the popper is sized to the search field, the panel fills it.
      width:
        filtersPanelWidth === 'full'
          ? '100%'
          : theme.spacing(filtersPanelWidth ?? defaultFiltersPanelWidth)
    },
    additionalFiltersButtons: {
      display: 'flex',
      flexDirection: 'row',
      justifyContent: 'space-between',
      marginTop: theme.spacing(2)
    },
    badge: {
      '& .MuiBadge-badge': {
        fontSize: theme.typography.caption.fontSize,
        height: theme.spacing(1.75),
        minWidth: theme.spacing(1.75),
        padding: theme.spacing(0, 0.5)
      }
    },
    filters: {
      maxWidth: theme.spacing(60),
      minWidth: theme.spacing(20),
      width: '100%'
    },
    statusFilter: {
      alignItems: 'start',
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'space-between',
      paddingInlineStart: theme.spacing(1)
    },
    statusFilterName: {
      fontWeight: theme.typography.fontWeightMedium
    }
  })
);
