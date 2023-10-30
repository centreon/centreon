import { ReactElement, ReactNode } from 'react';

import {
  Tooltip as MuiTooltip,
  TooltipProps as MuiTooltipProps
} from '@mui/material';

import { AriaLabelingAttributes } from '../../@types/aria-attributes';
import { DataTestAttributes } from '../../@types/data-attributes';

export type TooltipProps = {
  children: ReactElement;
  followCursor?: boolean;
  hasCaret?: boolean;
  isOpen?: boolean;
  label: ReactNode;
  position?:
    | 'top'
    | 'bottom'
    | 'left'
    | 'right'
    | 'bottom-end'
    | 'bottom-start'
    | 'left-end'
    | 'left-start'
    | 'right-end'
    | 'right-start'
    | 'top-end'
    | 'top-start';
} & AriaLabelingAttributes &
  DataTestAttributes &
  Omit<MuiTooltipProps, 'title'>;

const Tooltip = ({
  children,
  label,
  position = 'bottom',
  followCursor = true,
  isOpen,
  hasCaret = false,
  ...attr
}: TooltipProps): ReactElement => {
  return (
    <MuiTooltip
      arrow={hasCaret}
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
