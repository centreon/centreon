import { ReactNode } from 'react';

import { AvatarProps, Avatar as MUIAvatar } from '@mui/material';

import { useAvatarStyles } from './Avatar.styles';

interface Props extends AvatarProps {
  children: ReactNode;
  className?: string;
  compact?: boolean;
}

const Avatar = ({
  compact = false,
  children,
  className,
  ...attr
}: Props): JSX.Element => {
  const { classes, cx } = useAvatarStyles();

  return (
    <MUIAvatar
      {...attr}
      className={cx(classes.avatar, className)}
      data-compact={`${compact}`}
    >
      {children}
    </MUIAvatar>
  );
};

export default Avatar;
