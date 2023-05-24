import { CSSProperties, ForwardedRef, MouseEvent, forwardRef } from 'react';

import { isNil } from 'ramda';

import { Card } from '@mui/material';

import { useMemoComponent } from '../utils';

import { useDashboardItemStyles } from './Dashboard.styles';

interface DashboardItemProps {
  children: JSX.Element;
  className?: string;
  header?: JSX.Element;
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
      id,
      style,
      className,
      header,
      onMouseDown,
      onMouseUp,
      onTouchEnd
    }: DashboardItemProps,
    ref: ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes } = useDashboardItemStyles();

    const hasHeader = !isNil(header);

    const listeners = {
      onMouseDown,
      onMouseUp,
      onTouchEnd
    };

    const cardContainerListeners = !hasHeader ? listeners : {};

    return useMemoComponent({
      Component: (
        <div
          className={className}
          key={id}
          ref={ref}
          style={style}
          {...cardContainerListeners}
        >
          <Card className={classes.widgetContainer}>
            {header && (
              <div
                {...listeners}
                className={classes.widgetHeader}
                data-testid={`${id}_move_panel`}
                role="button"
              >
                {header}
              </div>
            )}
            <div className={classes.widgetContent}>{children}</div>
          </Card>
        </div>
      ),
      memoProps: [id, style, className, header]
    });
  }
);

export default Item;
