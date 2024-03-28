import { makeStyles } from 'tss-react/mui';

export const useTopBottomStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: 'auto minmax(50px, 1fr)'
  },
  linkToResourcesStatus: {
    '&:hover': {
      textDecoration: 'underline'
    },
    color: 'inherit',
    textDecoration: 'none'
  },
  loader: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  resourceLabel: {
    cursor: 'pointer',
    transform: 'translateY(50%)'
  },
  singleBarContainer: {
    cursor: 'pointer'
  }
}));
