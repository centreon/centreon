import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2),
    justifyContent: 'flex-end'
  },
  dashboardForm: {
    display: 'flex',
    flexDirection: 'column',
    maxWidth: '480px',

    width: '100%'
  }
}));

export { useStyles };
