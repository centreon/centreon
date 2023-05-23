import { CSSProperties, ForwardedRef, forwardRef, MouseEvent } from 'react';

import { isNil } from 'ramda';

import { Card } from '@mui/material';

/* eslint-disable-next-line import/no-named-as-default */
import useMemoComponent from '../utils/useMemoComponent';

import { useDashboardItemStyles } from './Dashboard.styles';

interface DashboardItemProps {
  children: JSX.Element;
  className?: string;
  header?: JSX.Element;
  key: string;
  onMouseDown?: (e: MouseEvent<HTMLDivElement>) => void;
  onMouseUp?: (e: MouseEvent<HTMLDivElement>) => void;
  onTouchEnd?: (e) => void;
  style?: CSSProperties;
}

const Item = forwardRef(
  (
    {
      children,
      key,
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
          key={key}
          ref={ref}
          style={style}
          {...cardContainerListeners}
        >
          <Card className={classes.widgetContainer}>
            {header && (
              <div {...listeners} className={classes.widgetHeader}>
                {header}
              </div>
            )}
            <div className={classes.widgetContent}>{children}</div>
          </Card>
        </div>
      ),
      memoProps: [key, style, className, header]
    });
  }
);

export default Item;
