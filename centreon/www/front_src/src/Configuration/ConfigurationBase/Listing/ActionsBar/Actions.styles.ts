import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles<{ hasWriteAccess?: boolean }>()(
  (theme, { hasWriteAccess }) => ({
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
    },
    searchBar: {
      alignItems: 'center',
      display: 'flex',
      justifyContent: hasWriteAccess ? 'center' : 'start',
      paddingInline: hasWriteAccess ? theme.spacing(1) : 0,
      width: '100%'
    }
  })
);
