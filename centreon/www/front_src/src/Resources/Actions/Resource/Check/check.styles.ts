import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center',
    display: 'flex'
  },
  // Condensed (icon-only): keep the two icons joined as a single pill.
  condensed: {
    borderRadius: '999px',
    overflow: 'hidden'
  },
  // Non-condensed: split-pill layout. The main button keeps its left side
  // rounded but flattens its right side; the chevron mirrors it (flat left,
  // rounded right). A 4px gap splits the two halves.
  container: {
    '& .MuiButton-root': {
      borderRadius: '18px 4px 4px 18px'
    },
    gap: theme.spacing(0.5)
  },
  iconArrow: {
    '&:hover': {
      backgroundColor: theme.palette.primary.dark
    },
    '&.Mui-disabled': {
      backgroundColor: theme.palette.action.disabledBackground
    },
    // Navy half matching the main button height (36px): flat left, rounded right.
    backgroundColor: theme.palette.primary.main,
    borderRadius: '4px 18px 18px 4px',
    color: theme.palette.common.white,
    height: theme.spacing(4.5),
    padding: 0,
    width: theme.spacing(4.5)
  }
}));
