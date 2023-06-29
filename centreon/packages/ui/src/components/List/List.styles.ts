import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()({
  list: {
    height: '100%',
    overflowY: 'auto'
  }
});

// TODO merge cleanup

export const useListItemTextStyles = makeStyles()((theme) => ({
  disabled: {
    color: theme.palette.action.disabled
  }
}));

export const useListItemAvatarStyles = makeStyles()((theme) => ({
  disabled: {
    backgroundColor: theme.palette.action.disabled
  }
}));
