import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    display: 'grid',
    gap: theme.spacing(1),
    gridTemplateColumns: 'min-content auto'
  },
  clearButton: {
    alignSelf: 'flex-start'
  },
  filters: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'center',
    paddingInline: theme.spacing(1),
    width: '100%'
  },
  search: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  },
  tooltipFilters: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    padding: theme.spacing(2, 3)
  }
}));
