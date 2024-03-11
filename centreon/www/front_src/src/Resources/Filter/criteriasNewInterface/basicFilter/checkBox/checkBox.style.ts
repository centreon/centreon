import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  checkbox: {
    justifyContent: 'start',
    minWidth: theme.spacing(5)
  },
  container: {
    gap: 0,
    justifyContent: 'space-between'
  },
  label: {
    paddingLeft: theme.spacing(0.25)
  },
  title: {
    fontSize: 13
  }
}));
