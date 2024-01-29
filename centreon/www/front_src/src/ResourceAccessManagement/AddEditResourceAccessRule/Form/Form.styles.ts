import { makeStyles } from 'tss-react/mui';

export const useActionButtonsStyles = makeStyles()((theme) => ({
  buttonContainer: {
    display: 'flex',
    gap: theme.spacing(2),
    justifyContent: 'end'
  }
}));
