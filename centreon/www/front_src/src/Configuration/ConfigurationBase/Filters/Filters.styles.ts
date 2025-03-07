import { makeStyles } from 'tss-react/mui';

export const useFilterStyles = makeStyles()((theme) => ({
  additionalFilters: {
    background: theme.palette.background.paper,
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5),
    marginTop: theme.spacing(1),
    padding: theme.spacing(2),
    width: theme.spacing(42)
  },
  additionalFiltersButtons: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: theme.spacing(2)
  },
  filters: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  },
  statusFilter: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingInlineStart: theme.spacing(1)
  },
  statusFilterName: {
    fontWeight: theme.typography.fontWeightMedium
  }
}));
