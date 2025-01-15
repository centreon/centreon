import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  search: {
    maxWidth: theme.spacing(50)
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
