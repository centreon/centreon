import { makeStyles } from 'tss-react/mui';

export const useTopBottomStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: 'minmax(50px, 1fr) minmax(100px, 1fr)'
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
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    transform: 'translateY(50%)',
    whiteSpace: 'nowrap',
    width: '100%'
  },
  singleBarContainer: {
    cursor: 'pointer'
  }
}));
