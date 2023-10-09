import {
  CSSProperties,
  ForwardedRef,
  forwardRef,
  MouseEvent,
  ReactElement
} from 'react';

import { isNil, propOr } from 'ramda';

import { Card, useTheme } from '@mui/material';

import { useMemoComponent } from '../utils';

import { useDashboardItemStyles } from './Dashboard.styles';

interface DashboardItemProps {
  canMove?: boolean;
  children: ReactElement;
  className?: string;
  disablePadding?: boolean;
  header?: ReactElement;
  id: string;
  onMouseDown?: (e: MouseEvent<HTMLDivElement>) => void;
  onMouseUp?: (e: MouseEvent<HTMLDivElement>) => void;
  onTouchEnd?: (e) => void;
  style?: CSSProperties;
}

const Item = forwardRef(
  (
    {
      children,
      style,
      className,
      header,
      onMouseDown,
      onMouseUp,
      onTouchEnd,
      id,
      disablePadding = false,
      canMove = false
    }: DashboardItemProps,
    ref: ForwardedRef<HTMLDivElement>
  ): ReactElement => {
    const hasHeader = !isNil(header);

    const { classes, cx } = useDashboardItemStyles({ hasHeader });
    const theme = useTheme();

    const listeners = {
      onMouseDown,
      onMouseUp,
      onTouchEnd
    };

    const cardContainerListeners = !hasHeader ? listeners : {};

    return useMemoComponent({
      Component: (
        <div
          {...cardContainerListeners}
          className={className}
          ref={ref}
          style={{
            ...style,
            width: `calc(${propOr('width', '0px', style)} - 12px)`
          }}
        >
          <Card className={classes.widgetContainer}>
            {header && (
              <div
                {...listeners}
                className={classes.widgetHeader}
                data-canMove={canMove}
                data-testid={`${id}_move_panel`}
              >
                {header}
              </div>
            )}
            <div
              className={cx(
                classes.widgetContent,
                !disablePadding && classes.widgetPadding
              )}
            >
              {children}
            </div>
          </Card>
        </div>
      ),
      memoProps: [style, className, header, theme.palette.mode, canMove]
    });
  }
);

export default Item;
