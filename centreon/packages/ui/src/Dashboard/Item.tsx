import {
  CSSProperties,
  ForwardedRef,
  MouseEvent,
  ReactElement,
  forwardRef,
  useEffect,
  useMemo
} from 'react';

import { useAtomValue } from 'jotai';
import { equals, isNil, omit, type } from 'ramda';

import { Card, useTheme } from '@mui/material';
import LoadingSkeleton from '../LoadingSkeleton';
import ExpandableContainer from '../components/ExpandableContainer';
import { Parameters } from '../components/ExpandableContainer/models';
import { useMemoComponent, useViewportIntersection } from '../utils';
import { useDashboardItemStyles } from './Dashboard.styles';
import { isResizingItemAtom } from './atoms';

interface DashboardItemProps {
  additionalMemoProps?: Array<unknown>;
  canMove?: boolean;
  children: ReactElement;
  className?: string;
  disablePadding?: boolean;
  header?: ReactElement | ((params: Parameters) => ReactElement);
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

    const isResizingItem = useAtomValue(isResizingItemAtom);

    const isResizing = useMemo(
      () => equals(id, isResizingItem),
      [isResizingItem, id]
    );

    const sanitizedReactGridLayoutClassName = useMemo(
      () => (isResizing ? className : className?.replace(' resizing ', '')),
      [className, isResizing]
    );

    const listeners = {
      onMouseDown,
      onMouseUp,
      onTouchEnd
    };

    const cardContainerListeners = !hasHeader ? listeners : {};

    useEffect(() => {
      if (isNil(ref?.current)) {
        return;
      }

      setElement(ref.current);
    }, [ref]);

    const newTransform =
      style?.transform &&
      `translate3d(${style.transform.match(/translate\(([a-z0-9\ \,\-]+)\)/)[1]}, 0px)`;

    return useMemoComponent({
      Component: (
        <div
          {...cardContainerListeners}
          className={sanitizedReactGridLayoutClassName}
          ref={ref}
          style={{
            ...omit(['transform'], style || {}),
            transform: newTransform
          }}
        >
          <ExpandableContainer>
            {({ isExpanded, label, key, ...rest }) => {
              const canControl = isExpanded ? false : canMove;

              const childrenHeader = equals(type(header), 'Function')
                ? (header as (params: Parameters) => ReactElement)({
                    isExpanded,
                    label,
                    ref,
                    key,
                    ...rest
                  })
                : header;

              return (
                <div key={key} className={classes.widgetSubContainer}>
                  <Card
                    className={classes.widgetContainer}
                    data-padding={!disablePadding}
                  >
                    {childrenHeader && (
                      <div
                        className={classes.widgetHeader}
                        data-can-move={canControl}
                      >
                        {canControl && (
                          <div
                            {...listeners}
                            className={classes.widgetHeaderDraggable}
                            data-testid={`${id}_move_panel`}
                          />
                        )}
                        {childrenHeader}
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
              );
            }}
          </ExpandableContainer>
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
