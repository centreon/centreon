import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    height: theme.spacing(22),
    width: theme.spacing(30),
    padding: theme.spacing(1),
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: theme.spacing(2)
  },
  notFound: {
    height: theme.spacing(10),
    width: theme.spacing(30),
    padding: theme.spacing(1)
  },
  body: {
    width: '100%',
    height: '100%',
    display: 'flex',
    justifyContent: 'space-between',
    gap: theme.spacing(1)
  },
  content: {
    width: '100%',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(0.5)
  },
  page: {
    fontWeight: theme.typography.fontWeightMedium
  },
  arrowContainer: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center'
  },
  icon: {
    color: theme.palette.text.primary
  },
  arrow: {
    fontSize: theme.spacing(2)
  },
  item: {
    color: 'inherit',
    textDecoration: 'none'
  },
  link: {
    '&:hover': {
      cursor: 'pointer',
      color: theme.palette.primary.main
    }
  }
}));
