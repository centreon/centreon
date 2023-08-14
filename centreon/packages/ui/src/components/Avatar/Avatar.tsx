import { ReactNode } from 'react';

import { Avatar as MUIAvatar } from '@mui/material';

import { useAvatarStyles } from './Avatar.styles';

interface Props {
  children: ReactNode;
  className?: string;
  compact?: boolean;
}

const Avatar = ({
  compact = false,
  children,
  className
}: Props): JSX.Element => {
  const { classes, cx } = useAvatarStyles();

  return (
    <MUIAvatar
      className={cx(classes.avatar, className)}
      data-compact={`${compact}`}
    >
      {children}
    </MUIAvatar>
  );
};

export default Avatar;
