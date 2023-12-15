import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  form: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: '1fr 1fr'
  },
  rightBox: {
    display: 'flex',
    flexDirection: 'column'
  }
}));

export const useActionButtonsStyles = makeStyles()((theme) => ({
  buttonContainer: {
    display: 'flex',
    gap: theme.spacing(2),
    justifyContent: 'end'
  }
}));
