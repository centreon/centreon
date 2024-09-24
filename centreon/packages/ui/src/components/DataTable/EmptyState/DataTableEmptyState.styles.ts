import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'row'
  },
  dataTableEmptyState: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(4),
    h2: {
      color: theme.palette.text.primary,
      font: 'normal normal 600 34px/36px Roboto',
      margin: '0'
    },
    height: '100%',
    justifyContent: 'center',
    minHeight: '30vh',
    width: '100%'
  },
  description: {
    width: '40%'
  }
}));

export { useStyles };
