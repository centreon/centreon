import React, { ReactElement, ReactNode } from 'react';

import { Button as MuiButton } from '@mui/material';

import { AriaLabelingAttributes } from '../../@types/aria-attributes';
import { DataTestAttributes } from '../../@types/data-attributes';

import { useStyles } from './Button.styles';

const muiVariantMap: Record<
  Required<ButtonProps>['variant'],
  'text' | 'outlined' | 'contained'
> = {
  ghost: 'text',
  primary: 'contained',
  secondary: 'outlined'
};

type ButtonProps = {
  children: ReactNode;
  disabled?: boolean;
  icon?: string | ReactNode;
  iconVariant?: 'none' | 'start' | 'end';
  isDanger?: boolean;
  onClick?: (e) => void;
  size?: 'small' | 'medium' | 'large';
  type?: 'button' | 'submit' | 'reset';
  variant?: 'primary' | 'secondary' | 'ghost';
} & AriaLabelingAttributes &
  DataTestAttributes;

const Button = ({
  children,
  variant = 'primary',
  size = 'medium',
  iconVariant = 'none',
  icon,
  type = 'button',
  disabled = false,
  onClick,
  isDanger = false,
  ...attr
}: ButtonProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiButton
      className={classes.button}
      data-icon-variant={iconVariant}
      data-is-danger={isDanger}
      data-size={size}
      data-variant={variant}
      disabled={disabled}
      size={size}
      type={type}
      variant={muiVariantMap[variant]}
      onClick={(e) => onClick?.(e)}
      {...attr}
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
