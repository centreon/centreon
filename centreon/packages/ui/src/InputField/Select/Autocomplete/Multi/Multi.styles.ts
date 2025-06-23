import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  deleteIcon: {
    height: theme.spacing(1.5),
    width: theme.spacing(1.5)
  },
  tag: {
    fontSize: theme.typography.caption.fontSize
  }
}));

export const useListboxStyles = makeStyles()((theme) => ({
  lisSubHeader: {
    width: '100%',
    background: theme.palette.background.default,
    padding: theme.spacing(0.5, 1, 0.5, 1.5),
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center'
  },
  dropdown: {
    width: '100%',
    background: theme.palette.background.paper
  }
}));
