import { makeStyles } from 'tss-react/mui';

export const useBlockButtonsStyles = makeStyles()((theme) => ({
  autocomplete: {
    width: theme.spacing(17)
  }
}));

export const useStyles = makeStyles()(() => ({
  menu: {
    display: 'flex',
    flexDirection: 'row'
  }
}));
