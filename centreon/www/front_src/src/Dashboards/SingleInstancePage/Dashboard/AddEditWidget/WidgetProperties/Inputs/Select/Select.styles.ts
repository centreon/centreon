import { makeStyles } from 'tss-react/mui';

export const useSelectStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    marginBottom: theme.spacing(2),
    width: 'fit-content'
  }
}));
