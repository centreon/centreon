import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  listEmptyState: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',

    width: '100%',
    height: '100%',
    minHeight: '30vh',

    gap: theme.spacing(4),

    h2: {
      font: 'normal normal 600 34px/36px Roboto',
      color: theme.palette.text.primary,
      margin: '0',
    }
  },
  actions: {
    display: 'flex',
    flexDirection: 'row',
  }

}));

export { useStyles };
