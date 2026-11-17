import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1.5)
  },
  bar: {
    display: 'flex'
  },
  moreActions: {
    [theme.breakpoints.down('md')]: {
      display: 'none'
    }
  },
  // Sizing the list does nothing: `ActionsList` merges the class it is given
  // with its own `width: 100%`, which wins on source order. Hence the Paper,
  // and the override below so it does not size to a list sizing to it.
  moreActionsMenu: {
    '& .MuiMenuList-root': {
      minWidth: '100%',
      width: 'max-content'
    },
    minWidth: theme.spacing(19),
    width: 'max-content'
  }
}));
