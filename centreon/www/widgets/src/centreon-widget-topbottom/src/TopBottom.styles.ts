import { makeStyles } from 'tss-react/mui';

export const useTopBottomStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: 'auto minmax(50px, 1fr)'
  },
  loader: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  resourceLabel: {
    transform: 'translateY(50%)'
  }
}));
