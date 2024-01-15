import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  item: {
    '&:hover:not(&[data-is-active="true"])': {
      backgroundColor: theme.palette.action.hover
    },
    '&[data-is-active="true"]': {
      backgroundColor: theme.palette.action.selected
    },
    backgroundColor: theme.palette.background.paper,
    borderRadius: theme.spacing(1),
    height: theme.spacing(7),
    padding: 0,
    width: theme.spacing(7)
  },
  items: {
    display: 'flex',
    gap: theme.spacing(1.5),
    marginTop: theme.spacing(1)
  }
}));
