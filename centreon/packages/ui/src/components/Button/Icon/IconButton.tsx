import { ReactNode } from 'react';

import { IconButton as MuiIconButton } from '@mui/material';

const muiColorMap: Record<
  Required<IconButtonProps>['variant'],
  'primary' | 'secondary' | 'default'
> = {
  ghost: 'default',
  primary: 'primary',
  secondary: 'secondary'
};

interface IconButtonProps {
  ariaLabel?: string;
  dataTestId?: string;
  disabled?: boolean;
  icon?: string | ReactNode;
  onClick?: (e) => void;
  size?: 'small' | 'medium' | 'large';
  variant?: 'primary' | 'secondary' | 'ghost';
}

const IconButton = ({
  variant = 'primary',
  size = 'medium',
  icon,
  disabled = false,
  onClick,
  dataTestId,
  ariaLabel
}: IconButtonProps): JSX.Element => {
  return (
    <MuiIconButton
      aria-label={ariaLabel}
      color={muiColorMap[variant]}
      data-size={size}
      data-testid={dataTestId}
      data-variant={variant}
      disabled={disabled}
      size={size}
      onClick={(e): void => onClick?.(e)}
    >
      {icon}
    </MuiIconButton>
  );
};

export { IconButton };
