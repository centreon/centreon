import React, { ReactNode } from 'react';

import { IconButton as MuiIconButton } from '@mui/material';

import { useStyles } from './IconButton.styles';

const muiColorMap: Record<
  Required<IconButtonProps>['variant'],
  'primary' | 'secondary' | 'default'
> = {
  ghost: 'default',
  primary: 'primary',
  secondary: 'secondary'
};

interface IconButtonProps {
  // TODO IconProps['name']
  disabled?: boolean;
  icon?: string | ReactNode;
  onClick?: (e) => void;
  size?: 'small' | 'medium' | 'large';
  variant?: 'primary' | 'secondary' | 'ghost';
}

const IconButton: React.FC<IconButtonProps> = ({
  variant = 'primary',
  size = 'medium',
  icon,
  disabled = false,
  onClick
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <MuiIconButton
      className={classes.iconButton}
      data-size={size}
      data-variant={variant}
      disabled={disabled}
      size={size}
      onClick={e => onClick?.(e)}
      // Mui overrides
      color={muiColorMap[variant]}
    >
      {icon}
    </MuiIconButton>
  );
};

export { IconButton };
