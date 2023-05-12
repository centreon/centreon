import { ReactNode } from 'react';

import {
  Button as MuiButton,
  ButtonProps as MuiButtonProps
} from '@mui/material';

import { useStyles } from './Button.styles';

const muiVariantMap: Record<
  Required<ButtonProps>['variant'],
  'text' | 'outlined' | 'contained'
> = {
  contained: 'contained',
  ghost: 'text',
  outlined: 'outlined'
};

interface ButtonProps {
  ariaLabel?: string;
  children: ReactNode;
  color?: MuiButtonProps['color'];
  dataTestId?: string;
  disabled?: boolean;
  icon?: string | ReactNode;
  iconVariant?: 'none' | 'start' | 'end';
  onClick?: (e) => void;
  size?: 'small' | 'medium' | 'large';
  type?: 'button' | 'submit' | 'reset';
  variant?: 'outlined' | 'contained' | 'ghost';
}

const Button = ({
  children,
  variant = 'contained',
  size = 'medium',
  iconVariant = 'none',
  icon,
  type = 'button',
  disabled = false,
  onClick,
  dataTestId,
  ariaLabel,
  color = 'primary'
}: ButtonProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <MuiButton
      aria-label={ariaLabel}
      className={classes.button}
      color={color}
      data-icon-variant={iconVariant}
      data-size={size}
      data-testId={dataTestId}
      data-variant={variant}
      disabled={disabled}
      size={size}
      type={type}
      variant={muiVariantMap[variant]}
      onClick={(e): void => onClick?.(e)}
      {...(iconVariant === 'start' && { startIcon: icon })}
      {...(iconVariant === 'end' && { endIcon: icon })}
    >
      {children}
    </MuiButton>
  );
};

export { Button };
