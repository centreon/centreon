import { makeStyles } from 'tss-react/mui';

export const useSelectorStyles = makeStyles()((theme) => ({
  checkbox: {
    padding: theme.spacing(0.5)
  },
  container: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-evenly'
  },
  label: {
    marginLeft: theme.spacing(-0.25),
    marginTop: theme.spacing(0.5)
  },
  selector: {
    width: '100%'
  }
}));
