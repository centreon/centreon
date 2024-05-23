import {
  CSSProperties,
  ForwardedRef,
  forwardRef,
  MouseEvent,
  ReactElement,
  useEffect
} from 'react';

import { isNil, prop } from 'ramda';

import { Card, useTheme } from '@mui/material';

import { useMemoComponent, useViewportIntersection } from '../utils';
import LoadingSkeleton from '../LoadingSkeleton';

import { useDashboardItemStyles } from './Dashboard.styles';

interface DashboardItemProps {
  additionalMemoProps?: Array<unknown>;
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

const Item = forwardRef<HTMLDivElement, DashboardItemProps>(
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
      canMove = false,
      additionalMemoProps = []
    }: DashboardItemProps,
    ref: ForwardedRef<HTMLDivElement>
  ): ReactElement => {
    const { isInViewport, setElement } = useViewportIntersection({
      rootMargin: '140px 0px 140px 0px'
    });
    const hasHeader = !isNil(header);

    const { classes, cx } = useDashboardItemStyles({ hasHeader });
    const theme = useTheme();

    const listeners = {
      onMouseDown,
      onMouseUp,
      onTouchEnd
    };

    const cardContainerListeners = !hasHeader ? listeners : {};

    useEffect(() => {
      if (isNil(ref)) {
        return;
      }

      setElement(ref.current);
    }, [ref]);

    return useMemoComponent({
      Component: (
        <div
          {...cardContainerListeners}
          className={className}
          ref={ref}
          style={{
            ...style,
            width: `calc(${prop('width', style) || '0px'} - 12px)`
          }}
        >
          <Card
            className={classes.widgetContainer}
            data-padding={!disablePadding}
          >
            {header && (
              <div className={classes.widgetHeader} data-canMove={canMove}>
                <div
                  {...listeners}
                  className={classes.widgetHeaderDraggable}
                  data-testid={`${id}_move_panel`}
                />
                {header}
              </div>
            )}
            <div
              className={cx(
                classes.widgetContent,
                !disablePadding && classes.widgetPadding
              )}
            >
              {!isInViewport ? (
                <LoadingSkeleton
                  animation={false}
                  data-widget-skeleton={id}
                  height="100%"
                  width="100%"
                />
              ) : (
                children
              )}
            </div>
          </Card>
        </div>
      ),
      memoProps: isInViewport
        ? [
            style,
            className,
            header,
            theme.palette.mode,
            canMove,
            isInViewport,
            ...additionalMemoProps
          ]
        : [isInViewport, theme.palette.mode, style]
    });
  }
);

export default Item;
