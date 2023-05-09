import React, { ReactNode } from 'react';
import { useStyles } from './Button.styles';
import { Button as MuiButton } from '@mui/material';


const muiVariantMap: Record<Required<ButtonProps>['variant'], 'text' | 'outlined' | 'contained'> = {
  primary: 'contained',
  secondary: 'outlined',
  ghost: 'text'
};

type ButtonProps = {
  children: ReactNode;
  variant?: 'primary' | 'secondary' | 'ghost';
  size?: 'small' | 'medium' | 'large';
  iconVariant?: 'none' | 'start' | 'end' | 'icon-only'; // TODO 'icon-only' support
  icon?: string | ReactNode; // TODO IconProps['name']
  disabled?: boolean; // TODO style
  type?: 'button' | 'submit' | 'reset';
  onClick?: (e) => void;
};

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
  const {classes} = useStyles();

  return (
    <MuiButton
      className={classes.button}
      data-variant={variant}
      data-size={size}
      data-icon-variant={iconVariant}
      data-icon={icon}
      type={type}
      disabled={disabled}
      onClick={e => onClick?.(e)}
      // Mui overrides
      color="primary"
      variant={muiVariantMap[variant]}
      size={size}
      {...(iconVariant === 'start' && {startIcon: icon})}
      {...(iconVariant === 'end' && {endIcon: icon})}
    >
      {children}
    </MuiButton>
  );
};

export { Button };