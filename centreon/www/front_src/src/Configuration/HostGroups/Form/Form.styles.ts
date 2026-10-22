import { makeStyles } from 'tss-react/mui';

export const useIconStyles = makeStyles()((theme) => ({
  icon: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  }
}));
