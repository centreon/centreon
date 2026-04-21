import {
  Button as MuiButton,
  type ButtonProps as MuiButtonProps
} from '@mui/material';

import { type ReactElement, type ReactNode, useMemo } from 'react';

import type { AriaLabelingAttributes } from '../../@types/aria-attributes';
import type { DataTestAttributes } from '../../@types/data-attributes';

const muiVariantMap: Record<
  Required<ButtonProps>['variant'],
  'text' | 'outlined' | 'contained'
> = {
  ghost: 'text',
  primary: 'contained',
  secondary: 'outlined'
};

export type ButtonProps = AriaLabelingAttributes &
  DataTestAttributes &
  Omit<MuiButtonProps, 'variant'> & {
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
  };

const sizeClasses: Record<string, string> = {
  medium: 'text-[16px] h-auto leading-[24px]',
  small: 'text-[14px] h-auto leading-[22px]'
};

const getButtonClasses = ({
  size,
  variant,
  isDanger,
  disabled
}: {
  disabled: boolean;
  isDanger: boolean;
  size: string;
  variant: string;
}): string => {
  const classes = ['text-nowrap', sizeClasses[size] ?? ''];

  if (size === 'small' && variant === 'primary') {
    classes.push('px-4');
  }

  if (!disabled) {
    if (variant === 'primary') {
      classes.push(isDanger ? 'bg-error-main' : 'bg-primary-main');
    }
    if (variant === 'secondary') {
      classes.push(
        isDanger
          ? 'border-error-main text-error-main'
          : 'border-primary-main text-primary-main'
      );
    }
  }

  return classes.filter(Boolean).join(' ');
};

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
  const MuiOverrideProps = useMemo(
    () => ({
      color: 'primary' as const,
      ...(iconVariant === 'start' && { startIcon: icon }),
      ...(iconVariant === 'end' && { endIcon: icon })
    }),
    [icon, iconVariant]
  );

  const buttonClasses = getButtonClasses({ disabled, isDanger, size, variant });

  return (
    <MuiButton
      className={`${buttonClasses} ${className}`}
      data-icon-variant={iconVariant}
      data-is-danger={isDanger}
      data-size={size}
      data-variant={variant}
      disabled={disabled}
      onClick={(e) => onClick?.(e)}
      size={size}
      type={type}
      variant={muiVariantMap[variant]}
      {...MuiOverrideProps}
      {...attr}
    >
      {children}
    </MuiButton>
  );
};

export { Button };
