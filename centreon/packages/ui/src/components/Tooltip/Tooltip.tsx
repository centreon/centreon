import { ReactElement, ReactNode } from 'react';

import { Tooltip as MuiTooltip } from '@mui/material';

import { AriaLabelingAttributes } from '../../@types/aria-attributes';
import { DataTestAttributes } from '../../@types/data-attributes';

import { useStyles } from './Tooltip.styles';

export type TooltipProps = {
  children: ReactElement;
  followCursor?: boolean;
  hasCaret?: boolean;
  isOpen?: boolean;
  label: ReactNode;
  position?: 'top' | 'bottom' | 'left' | 'right';
} & AriaLabelingAttributes &
  DataTestAttributes;

const Tooltip = ({
  children,
  label,
  position = 'bottom',
  followCursor = true,
  isOpen,
  hasCaret = false,
  ...attr
}: TooltipProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiTooltip
      arrow={hasCaret}
      className={classes.tooltip}
      followCursor={followCursor}
      open={isOpen}
      placement={position}
      title={label}
      {...attr}
    >
      {children}
    </MuiTooltip>
  );
};

export { Tooltip };
