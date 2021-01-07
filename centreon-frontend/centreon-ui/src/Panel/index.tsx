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

type StylesProps = Pick<Props, 'headerBackgroundColor' | 'width'>;

const useStyles = makeStyles<Theme, StylesProps>((theme) => ({
  container: {
    height: '100%',
    display: 'grid',
    gridTemplate: 'auto auto 1fr / 1fr',
    overflow: 'hidden',
    width: ({ width }) => width,
  },
  header: {
    gridArea: '1 / 1 / 2 / 1',
    padding: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: '1fr auto',
    alignItems: 'center',
    backgroundColor: ({ headerBackgroundColor }) => headerBackgroundColor,
  },
  divider: {
    gridArea: '2 / 1 / 3 / 1',
  },
  body: {
    gridArea: '3 / 1 / 4 / 1',
    display: 'grid',
    gridTemplateRows: 'auto 1fr',
    height: '100%',
  },
  contentContainer: {
    backgroundColor: theme.palette.background.default,
    position: 'relative',
  },
  content: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    top: 0,
    overflow: 'auto',
  },
  dragger: {
    cursor: 'ew-resize',
    position: 'absolute',
    bottom: 0,
    right: ({ width }) => width,
    top: 0,
    width: 5,
    zIndex: theme.zIndex.drawer,
  },
}));

export interface Tab {
  tab: JSX.Element;
  id: number;
}

interface Props {
  header: React.ReactElement;
  selectedTab: React.ReactElement;
  tabs?: Array<JSX.Element>;
  selectedTabId?: number;
  onTabSelect?: (event, id: number) => void;
  onClose?: () => void;
  labelClose?: string;
  width?: number;
  minWidth?: number;
  headerBackgroundColor?: string;
  onResize?: (newWidth: number) => void;
}

const Panel = ({
  header,
  tabs = [],
  selectedTabId = 0,
  selectedTab,
  onClose,
  onTabSelect = () => undefined,
  labelClose = 'Close',
  width = 550,
  minWidth = 550,
  headerBackgroundColor,
  onResize,
}: Props): JSX.Element => {
  const classes = useStyles({ width, headerBackgroundColor });

  const resize = () => {
    document.addEventListener('mouseup', releaseMouse, true);
    document.addEventListener('mousemove', moveMouse, true);
  };

  const releaseMouse = () => {
    document.removeEventListener('mouseup', releaseMouse, true);
    document.removeEventListener('mousemove', moveMouse, true);
  };

  const moveMouse = React.useCallback((e) => {
    e.preventDefault();

    const maxWidth = window.innerWidth * 0.85;
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
      direction="left"
      in
      timeout={{
        enter: 150,
        exit: 50,
      }}
    >
      <Paper elevation={2} className={classes.container}>
        {!isNil(onResize) && (
          <div className={classes.dragger} onMouseDown={resize} role="none" />
        )}
        {header && (
          <>
            <div className={classes.header}>
              {header}
              {onClose && (
                <IconButton
                  title={labelClose}
                  ariaLabel={labelClose}
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
          <AppBar position="static" color="default">
            {!isEmpty(tabs) && (
              <Tabs
                variant="fullWidth"
                value={selectedTabId}
                indicatorColor="primary"
                textColor="primary"
                onChange={onTabSelect}
              >
                {tabs.map((tab) => tab)}
              </Tabs>
            )}
          </AppBar>
          <div className={classes.contentContainer}>
            <div className={classes.content}>{selectedTab}</div>
          </div>
        </div>
      </Paper>
    </Slide>
  );
};

export default Panel;
