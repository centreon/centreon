import { makeStyles } from 'tss-react/mui';

export const useShareInputStyles = makeStyles()((theme) => ({
  inputs: {
    display: 'grid',
    gap: theme.spacing(1),
    gridTemplateColumns: '1fr min-content min-content'
  }
}));

export const useContactSwitchStyles = makeStyles()((theme) => ({
  inputs: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  }
}));
