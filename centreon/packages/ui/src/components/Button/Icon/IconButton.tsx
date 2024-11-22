import { ReactElement, ReactNode } from 'react';

import {
  IconButton as MuiIconButton,
  IconButtonProps as MuiIconButtonProps
} from '@mui/material';

import { AriaLabelingAttributes } from '../../../@types/aria-attributes';
import { DataTestAttributes } from '../../../@types/data-attributes';

import { useStyles } from './IconButton.styles';

const muiColorMap: Record<
  Required<IconButtonProps>['variant'],
  'primary' | 'secondary' | 'default'
> = {
  ghost: 'default',
  primary: 'primary',
  secondary: 'secondary'
};

type IconButtonProps = {
  disabled?: boolean;
  icon?: string | ReactNode;
  onClick?: (e) => void;
  size?: 'small' | 'medium' | 'large';
  variant?: 'primary' | 'secondary' | 'ghost';
} & AriaLabelingAttributes &
  DataTestAttributes &
  MuiIconButtonProps;

/**
 * @todo re-factor as `iconVariant: 'icon-only'` Button variant, and remove IconButton component (reason: code duplication)
 */
const IconButton = ({
  variant = 'primary',
  size = 'medium',
  icon,
  disabled = false,
  onClick,
  ...attr
}: IconButtonProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiIconButton
      className={classes.iconButton}
      data-size={size}
      data-variant={variant}
      disabled={disabled}
      size={size}
      onClick={(e) => onClick?.(e)}
      {...attr}
      // Mui overrides
      color={muiColorMap[variant]}
    >
      {icon}
    </MuiIconButton>
  );
};

export { IconButton };
