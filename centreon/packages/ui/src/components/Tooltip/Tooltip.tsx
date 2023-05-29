import { ReactElement, ReactNode } from 'react';

import { Tooltip as MuiTooltip } from '@mui/material';

import { useStyles } from './Tooltip.styles';

type TooltipProps = {
  children: ReactElement;
  followCursor?: boolean;
  hasCaret?: boolean;
  isOpen?: boolean;
  label: ReactNode;
  position?: 'top' | 'bottom' | 'left' | 'right';
};

const Tooltip = ({
  children,
  label,
  position = 'bottom',
  followCursor = true,
  isOpen,
  hasCaret = false
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
    >
      {children}
    </MuiTooltip>
  );
};

export { Tooltip };
