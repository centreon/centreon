import { ReactElement } from 'react';

import { Avatar as MuiAvatar, ListItemAvatar } from '@mui/material';

type AvatarProps = {
  children: ReactElement | string;
};
export const Avatar = ({ children }: AvatarProps): ReactElement => {
  return (
    <ListItemAvatar data-element="avatar">
      <MuiAvatar>{children}</MuiAvatar>
    </ListItemAvatar>
  );
};
