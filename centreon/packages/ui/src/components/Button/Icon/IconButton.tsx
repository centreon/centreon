import React, { ReactNode } from 'react';
import { useStyles } from './IconButton.styles';
import { IconButton as MuiIconButton } from '@mui/material';


const muiColorMap: Record<Required<IconButtonProps>['variant'], 'primary' | 'secondary' | 'default'> = {
  primary: 'primary',
  secondary: 'secondary',
  ghost: 'default'
};

type IconButtonProps = {
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'small' | 'medium' | 'large';
  icon?: string | ReactNode; // TODO IconProps['name']
  disabled?: boolean;
  onClick?: (e) => void;
};

const IconButton: React.FC<IconButtonProps> = ({
  variant = 'primary',
  size = 'medium',
  icon,
  disabled = false,
  onClick
}): JSX.Element => {
  const {classes} = useStyles();

  return (
    <MuiIconButton
      className={classes.iconButton}
      data-variant={variant}
      data-size={size}
      disabled={disabled}
      onClick={e => onClick?.(e)}
      // Mui overrides
      color={muiColorMap[variant]}
      size={size}
    >
      {icon}
    </MuiIconButton>
  );
};

export { IconButton };