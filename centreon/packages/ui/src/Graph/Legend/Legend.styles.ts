import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    padding: theme.spacing(1),
    border: `1px solid ${theme.palette.divider}`,
    borderRadius: theme.shape.borderRadius,
    width: 'fit-content'
  }
}));
