import { CSSProperties, ForwardedRef, MouseEvent, forwardRef } from 'react';

import { isNil } from 'ramda';

import { Card } from '@mui/material';

import useMemoComponent from '../utils/useMemoComponent';

import { useDashboardItemStyles } from './useDashboardStyles';

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

const DashboardItem = forwardRef(
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
            <div {...listeners} className={classes.widgetHeader}>
              {header}
            </div>
            <div className={classes.widgetContent}>{children}</div>
          </Card>
        </div>
      ),
      memoProps: [key, style, className, header]
    });
  }
);

export default DashboardItem;
