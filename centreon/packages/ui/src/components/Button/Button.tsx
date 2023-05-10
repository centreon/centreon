import React, { ReactNode } from 'react';

import { Button as MuiButton } from '@mui/material';

import { useStyles } from './Button.styles';

const muiVariantMap: Record<
  Required<ButtonProps>['variant'],
  'text' | 'outlined' | 'contained'
> = {
  ghost: 'text',
  primary: 'contained',
  secondary: 'outlined'
};

interface ButtonProps {
  children: ReactNode;
  // TODO IconProps['name']
  disabled?: boolean;
  icon?: string | ReactNode;
  iconVariant?: 'none' | 'start' | 'end';
  onClick?: (e) => void;
  size?: 'small' | 'medium' | 'large';
  type?: 'button' | 'submit' | 'reset';
  variant?: 'primary' | 'secondary' | 'ghost';
}

const Button: React.FC<ButtonProps> = ({
  children,
  variant = 'primary',
  size = 'medium',
  iconVariant = 'none',
  icon,
  type = 'button',
  disabled = false,
  onClick
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <MuiButton
      className={classes.button}
      data-icon-variant={iconVariant}
      data-size={size}
      data-variant={variant}
      disabled={disabled}
      size={size}
      type={type}
      variant={muiVariantMap[variant]}
      onClick={e => onClick?.(e)}
      // Mui overrides
      color="primary"
      {...(iconVariant === 'start' && { startIcon: icon })}
      {...(iconVariant === 'end' && { endIcon: icon })}
    >
      {children}
    </MuiButton>
  );
};

export { Button };
