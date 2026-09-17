import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  arrow: {
    fontSize: theme.spacing(2)
  },
  arrowContainer: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between'
  },
  body: {
    display: 'flex',
    gap: theme.spacing(1),
    height: '100%',
    justifyContent: 'space-between',
    width: '100%'
  },
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    height: theme.spacing(22),
    justifyContent: 'space-between',
    padding: theme.spacing(1),
    width: theme.spacing(30)
  },
  content: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(0.5),
    width: '100%'
  },
  icon: {
    color: theme.palette.text.primary
  },
  item: {
    color: 'inherit',
    textDecoration: 'none'
  },
  link: {
    '&:hover': {
      color: theme.palette.primary.main,
      cursor: 'pointer'
    }
  },
  notFound: {
    height: theme.spacing(10),
    padding: theme.spacing(1),
    width: theme.spacing(30)
  },
  page: {
    fontWeight: theme.typography.fontWeightMedium
  }
}));
