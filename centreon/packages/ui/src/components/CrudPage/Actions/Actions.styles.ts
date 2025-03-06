import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  search: {
    maxWidth: theme.spacing(60),
    width: '100%'
  },
  filters: {
    width: '100%',
    paddingInline: theme.spacing(1),
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
  },
  actions: {
    display: 'grid',
    gridTemplateColumns: 'min-content auto',
    gap: theme.spacing(1)
  },
  clearButton: {
    alignSelf: 'flex-start'
  },
  tooltipFilters: {
    padding: theme.spacing(2, 3),
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  }
}));
