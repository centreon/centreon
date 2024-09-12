import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  search: {
    maxWidth: theme.spacing(50)
  },
  filtersContainer: {
    width: theme.spacing(30),
    padding: theme.spacing(2, 3),
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  }
}));
