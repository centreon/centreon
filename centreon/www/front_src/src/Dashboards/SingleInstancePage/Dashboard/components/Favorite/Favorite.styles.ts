import { makeStyles } from 'tss-react/mui';

export const useFavoriteStyle = makeStyles()((theme) => ({
  button: {
    height: theme.spacing(3.5),
    width: theme.spacing(3.5)
  }
}));
