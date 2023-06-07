import { ReactElement } from 'react';

import { ListItemAvatar, Avatar as MUIAvatar } from '@mui/material';

type AvatarProps = {
  children: ReactElement | string;
};
export const Avatar = ({ children }: AvatarProps): JSX.Element => {
  return (
    <ListItemAvatar>
      <MUIAvatar>{children}</MUIAvatar>
    </ListItemAvatar>
  );
};
