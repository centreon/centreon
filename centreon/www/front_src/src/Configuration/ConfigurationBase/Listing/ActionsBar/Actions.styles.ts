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
    // On the menu's Paper, not on the list inside it: `ActionsList` merges the
    // class it is given with its own `width: 100%`, which wins on source order,
    // so sizing the list did nothing. Content-sized rather than a constant, so
    // the menu follows the longest entry — which changes when a module adds an
    // action, and again in translation.
    moreActionsMenu: {
      // The list inside carries `width: 100%` from `ActionsList`, so on its own
      // the Paper would size to a list that sizes to the Paper. Overriding it
      // from here wins on specificity and lets the intrinsic width resolve.
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
