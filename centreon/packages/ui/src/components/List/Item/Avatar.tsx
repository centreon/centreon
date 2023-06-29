import { ReactElement } from 'react';

import {
  Avatar as MuiAvatar,
  ListItemAvatar as MuiListItemAvatar
} from '@mui/material';

import { useStyles } from './ListItem.styles';

type AvatarProps = {
  children: ReactElement | string;
};
export const Avatar = ({ children }: AvatarProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiListItemAvatar className={classes.avatar} data-element="avatar">
      <MuiAvatar>{children}</MuiAvatar>
    </MuiListItemAvatar>
  );
};
