import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import { Card, useTheme } from '@mui/material';

import { useAtomValue } from 'jotai';
import { equals, isNil, omit, type } from 'ramda';
import {
  type CSSProperties,
  type MouseEvent,
  type ReactElement,
  RefObject,
  useEffect,
  useMemo
} from 'react';

import ExpandableContainer from '../components/ExpandableContainer';
import type { Parameters } from '../components/ExpandableContainer/models';
import { useMemoComponent, useViewportIntersection } from '../utils';
import { isResizingItemAtom } from './atoms';
import { useDashboardItemStyles } from './Dashboard.styles';

interface DashboardItemProps {
  additionalMemoProps?: Array<unknown>;
  canMove?: boolean;
  children: Array<
    | ReactElement
    | (({ isInViewport }: { isInViewport: boolean }) => ReactElement)
  >;
  className?: string;
  disablePadding?: boolean;
  hasVisibleHeader?: boolean;
  header?: ReactElement | ((params: Parameters) => ReactElement);
  id: string;
  onMouseDown?: (e: MouseEvent<HTMLDivElement>) => void;
  onMouseUp?: (e: MouseEvent<HTMLDivElement>) => void;
  onTouchEnd?: (e: React.TouchEvent<HTMLDivElement>) => void;
  overlayActions?: ReactElement | ((params: Parameters) => ReactElement);
  overlayInfo?: ReactElement | ((params: Parameters) => ReactElement);
  style?: CSSProperties;
  ref?: RefObject<HTMLDivElement>;
}

const Item = ({
  children,
  style,
  className,
  header,
  hasVisibleHeader,
  onMouseDown,
  onMouseUp,
  onTouchEnd,
  id,
  disablePadding = false,
  canMove = false,
  additionalMemoProps = [],
  overlayActions,
  overlayInfo,
  ref
}: DashboardItemProps): ReactElement => {
  const { isInViewport, setElement } = useViewportIntersection({
    rootMargin: '140px 0px 140px 0px'
  });
  const hasHeader = !isNil(header);
  const showHeaderSpace = hasVisibleHeader ?? hasHeader;

  const { classes, cx } = useDashboardItemStyles({
    hasHeader: showHeaderSpace
  });
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
  }, [ref, setElement]);

  const newTransform =
    style?.transform &&
    `translate3d(${style.transform.match(/translate\(([a-z0-9 ,-]+)\)/)?.[1]}, 0px)`;

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

            const expandableParams = {
              isExpanded,
              key,
              label,
              ref: ref as RefObject<HTMLDivElement>,
              ...rest
            };

            const childrenHeader = equals(type(header), 'Function')
              ? (header as (params: Parameters) => ReactElement)(
                  expandableParams
                )
              : (header as ReactElement);

            const childrenOverlayActions = equals(
              type(overlayActions),
              'Function'
            )
              ? (overlayActions as (params: Parameters) => ReactElement)(
                  expandableParams
                )
              : (overlayActions as ReactElement);

            const childrenOverlayInfo = equals(type(overlayInfo), 'Function')
              ? (overlayInfo as (params: Parameters) => ReactElement)(
                  expandableParams
                )
              : (overlayInfo as ReactElement);

            return (
              <div className={classes.widgetSubContainer} key={key}>
                <Card
                  className={classes.widgetContainer}
                  data-padding={!disablePadding}
                >
                  {(childrenOverlayActions || childrenOverlayInfo) && (
                    <div
                      className={cx(
                        'cf-widget-overlay-corner',
                        classes.widgetOverlayCorner
                      )}
                    >
                      {childrenOverlayActions && (
                        <div
                          className={cx(
                            'cf-widget-overlay-actions',
                            classes.widgetOverlayActions
                          )}
                        >
                          {canControl && (
                            <div
                              {...listeners}
                              className={cx(
                                'cf-widget-drag-handle',
                                classes.widgetOverlayDragHandle
                              )}
                              data-testid={`${id}_move_panel`}
                            >
                              <DragIndicatorIcon fontSize="small" />
                            </div>
                          )}
                          {childrenOverlayActions}
                        </div>
                      )}
                      {childrenOverlayInfo && (
                        <div
                          className={cx(
                            'cf-widget-overlay-info',
                            classes.widgetOverlayInfo
                          )}
                        >
                          {childrenOverlayInfo}
                        </div>
                      )}
                    </div>
                  )}
                  {childrenHeader && (
                    <div
                      className={cx(
                        classes.widgetHeader,
                        !showHeaderSpace && classes.widgetHeaderCollapsed
                      )}
                    >
                      {childrenHeader}
                    </div>
                  )}
                  <div
                    className={cx(
                      classes.widgetContent,
                      !disablePadding && classes.widgetPadding
                    )}
                  >
                    {children.map((child) =>
                      typeof child === 'function'
                        ? child({ isInViewport })
                        : child
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
          hasVisibleHeader,
          overlayActions,
          overlayInfo,
          theme.palette.mode,
          canMove,
          isInViewport,
          ...additionalMemoProps
        ]
      : [isInViewport, theme.palette.mode, style]
  });
};

export default Item;
