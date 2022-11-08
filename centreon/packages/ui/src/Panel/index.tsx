/* eslint-disable react/no-unused-prop-types */
import * as React from 'react';

import { isEmpty, isNil } from 'ramda';

import {
  makeStyles,
  Paper,
  Slide,
  Divider,
  AppBar,
  Tabs,
  Theme,
} from '@material-ui/core';
import IconClose from '@material-ui/icons/Clear';

import IconButton from '../Button/Icon';

import { minTabHeight } from './Tab';

type StylesProps = Pick<Props, 'headerBackgroundColor' | 'width'>;

const useStyles = makeStyles<Theme, StylesProps>((theme) => ({
  body: {
    display: 'grid',
    gridArea: '3 / 1 / 4 / 1',
    gridTemplateRows: 'auto 1fr',
    height: '100%',
  },
  container: {
    display: 'grid',
    gridTemplate: 'auto auto 1fr / 1fr',
    height: '100%',
    overflow: 'hidden',
    width: ({ width }): number | undefined => width,
  },
  content: {
    bottom: 0,
    left: 0,
    overflow: 'auto',
    position: 'absolute',
    right: 0,
    top: 0,
  },
  contentContainer: {
    backgroundColor: theme.palette.background.default,
    position: 'relative',
  },
  divider: {
    gridArea: '2 / 1 / 3 / 1',
  },
  dragger: {
    bottom: 0,
    cursor: 'ew-resize',
    position: 'absolute',
    right: ({ width }): number | undefined => width,
    top: 0,
    width: 5,
    zIndex: theme.zIndex.drawer,
  },
  header: {
    alignItems: 'center',
    backgroundColor: ({ headerBackgroundColor }): string | undefined =>
      headerBackgroundColor,
    display: 'grid',
    gridArea: '1 / 1 / 2 / 1',
    gridTemplateColumns: '1fr auto',
    padding: theme.spacing(1),
  },
  tabs: {
    minHeight: minTabHeight,
  },
}));

export interface Tab {
  id: number;
  tab: JSX.Element;
}

export interface Props {
  header: React.ReactElement;
  headerBackgroundColor?: string;
  labelClose?: string;
  memoProps?: Array<unknown>;
  minWidth?: number;
  onClose?: () => void;
  onResize?: (newWidth: number) => void;
  onTabSelect?: (event, id: number) => void;
  selectedTab: React.ReactElement;
  selectedTabId?: number;
  tabs?: Array<JSX.Element>;
  width?: number;
}

const Panel = React.forwardRef<HTMLDivElement, Props>(
  (
    {
      header,
      tabs = [],
      selectedTabId = 0,
      selectedTab,
      onClose,
      onTabSelect = (): undefined => undefined,
      labelClose = 'Close',
      width = 550,
      minWidth = 550,
      headerBackgroundColor,
      onResize,
    }: Omit<Props, 'memoProps'>,
    ref,
  ): JSX.Element => {
    const classes = useStyles({ headerBackgroundColor, width });

    const getMaxWidth = (): number => window.innerWidth * 0.85;

    const resizeWindow = (): void => {
      const maxWidth = getMaxWidth();

      if (width > maxWidth) {
        onResize?.(maxWidth);
      }
    };

    React.useEffect(() => {
      window.addEventListener('resize', resizeWindow);

      return (): void => {
        window.removeEventListener('resize', resizeWindow);
      };
    }, []);

    const resize = (): void => {
      document.addEventListener('mouseup', releaseMouse, true);
      document.addEventListener('mousemove', moveMouse, true);
    };

    const releaseMouse = (): void => {
      document.removeEventListener('mouseup', releaseMouse, true);
      document.removeEventListener('mousemove', moveMouse, true);
    };

    const moveMouse = React.useCallback((e) => {
      e.preventDefault();

      const maxWidth = getMaxWidth();
      const newWidth = document.body.clientWidth - e.clientX;

      const getResizedWidth = (): number => {
        if (newWidth <= minWidth) {
          return minWidth;
        }

        if (newWidth > maxWidth) {
          return maxWidth;
        }

        return newWidth;
      };

      onResize?.(getResizedWidth());
    }, []);

    return (
      <Slide
        in
        direction="left"
        timeout={{
          enter: 150,
          exit: 50,
        }}
      >
        <Paper className={classes.container} elevation={2}>
          {!isNil(onResize) && (
            <div className={classes.dragger} role="none" onMouseDown={resize} />
          )}
          {header && (
            <>
              <div className={classes.header}>
                {header}
                {onClose && (
                  <IconButton
                    ariaLabel={labelClose}
                    title={labelClose}
                    onClick={onClose}
                  >
                    <IconClose color="action" />
                  </IconButton>
                )}
              </div>
              <Divider className={classes.divider} />
            </>
          )}
          <div className={classes.body}>
            <AppBar color="default" position="static">
              {!isEmpty(tabs) && (
                <Tabs
                  className={classes.tabs}
                  indicatorColor="primary"
                  textColor="primary"
                  value={selectedTabId}
                  variant="fullWidth"
                  onChange={onTabSelect}
                >
                  {tabs.map((tab) => tab)}
                </Tabs>
              )}
            </AppBar>
            <div className={classes.contentContainer} ref={ref}>
              <div className={classes.content}>{selectedTab}</div>
            </div>
          </div>
        </Paper>
      </Slide>
    );
  },
);

export default Panel;
