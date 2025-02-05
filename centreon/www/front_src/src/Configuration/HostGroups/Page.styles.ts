import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  listing: {
    height: `calc(100vh - ${theme.spacing(21)})`,
    marginTop: theme.spacing(1),
    overflowY: 'auto',
    width: '100%'
  },
  page: {
    padding: theme.spacing(3, 3, 0)
  },
  pageHeader: {
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    fontWeight: theme.typography.fontWeightBold,
    paddingBottom: theme.spacing(1.5)
  }
}));
