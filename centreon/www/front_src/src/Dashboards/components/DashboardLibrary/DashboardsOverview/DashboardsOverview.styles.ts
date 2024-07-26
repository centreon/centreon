import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    height: `calc(100vh - ${theme.spacing(20)})`,
    overflowY: 'auto',
    width: '100%'
  },
  dashboardItemContainer: {
    position: 'relative'
  },
  thumbnailFallbackIcon: {
    backgroundColor: theme.palette.background.default,
    borderRadius: '50%',
    height: theme.spacing(3),
    position: 'absolute',
    right: theme.spacing(1),
    top: theme.spacing(1),
    width: theme.spacing(3)
  }
}));
