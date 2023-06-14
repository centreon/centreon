import { ReactElement, ReactNode, useMemo } from 'react';

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

export type ButtonProps = {
  children: ReactNode;
  className?: string;
  disabled?: boolean;
  icon?: string | ReactNode;
  iconVariant?: 'none' | 'start' | 'end';
  isDanger?: boolean;
  onClick?: (e) => void;
  ref?: React.Ref<HTMLButtonElement>;
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
  className = '',
  ...attr
}: ButtonProps): ReactElement => {
  const { classes, cx } = useStyles();

  const MuiOverrideProps = useMemo(
    () => ({
      color: 'primary' as const,
      ...(iconVariant === 'start' && { startIcon: icon }),
      ...(iconVariant === 'end' && { endIcon: icon })
    }),
    [icon, iconVariant]
  );

  return (
    <MuiButton
      className={cx(classes.button, className)}
      data-icon-variant={iconVariant}
      data-is-danger={isDanger}
      data-size={size}
      data-variant={variant}
      disabled={disabled}
      size={size}
      type={type}
      variant={muiVariantMap[variant]}
      onClick={(e) => onClick?.(e)}
      {...MuiOverrideProps}
      {...attr}
    >
      {children}
    </MuiButton>
  );
};

export { Button };
