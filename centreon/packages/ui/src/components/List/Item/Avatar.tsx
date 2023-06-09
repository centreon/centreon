import { ReactElement } from 'react';

import { ListItemAvatar, Avatar as MuiAvatar } from '@mui/material';

type AvatarProps = {
  children: ReactElement | string;
};
export const Avatar = ({ children }: AvatarProps): JSX.Element => {
  return (
    <ListItemAvatar data-element="avatar">
      <MuiAvatar>{children}</MuiAvatar>
    </ListItemAvatar>
  );
};
