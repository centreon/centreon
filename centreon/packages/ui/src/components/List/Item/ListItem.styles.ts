import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  avatar: {
    disabled: {
      backgroundColor: theme.palette.action.disabled
    }
  },
  listItem: {
    maxWidth: '520px'
  },
  text: {
    disabled: {
      color: theme.palette.action.disabled
    }
  }
}));
