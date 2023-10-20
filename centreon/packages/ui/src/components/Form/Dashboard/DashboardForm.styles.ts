import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()(() => ({
  dashboardForm: {
    display: 'flex',
    flexDirection: 'column',
    maxWidth: '480px',
    width: '100%'
  }
}));

const useGlobalRefreshIntervalStyles = makeStyles()((theme) => ({
  globalRefreshInterval: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));

export { useStyles, useGlobalRefreshIntervalStyles };
