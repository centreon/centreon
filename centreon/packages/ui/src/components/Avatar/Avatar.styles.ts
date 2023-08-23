import { makeStyles } from 'tss-react/mui';

export const useAvatarStyles = makeStyles()((theme) => ({
  avatar: {
    '&[data-compact="true"]': {
      fontSize: theme.typography.body1.fontSize,
      height: theme.spacing(2),
      width: theme.spacing(2)
    }
  }
}));
