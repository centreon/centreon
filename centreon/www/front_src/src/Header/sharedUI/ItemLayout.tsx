import { useState, useEffect } from 'react';

import { makeStyles } from 'tss-react/mui';

import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import { Badge, ClickAwayListener } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  button: {
    '& > svg': {
      height: '0.9em',
      margin: `-${theme.spacing(0.5)}`,
      [theme.breakpoints.down(768)]: {
        margin: `-${theme.spacing(0.5)}`
      }
    },
    appearance: 'none',
    background: 'none',
    border: 0,
    color: theme.palette.common.white,
    cursor: 'pointer',
    display: 'flex',

    [theme.breakpoints.up(768)]: {
      alignItems: 'center',
      flexFlow: 'row no-wrap',
      marginTop: theme.spacing(0.5)
    },

    [theme.breakpoints.down(768)]: {
      alignItems: 'center',
      flexFlow: 'column wrap',
      order: 1
    },

    padding: '0'
  },
  container: {
    position: 'relative'
  },
  header: {
    [theme.breakpoints.down(768)]: {
      display: 'flex',
      flexFlow: 'row no-wrap'
    }
  },
  iconWrapper: {
    [theme.breakpoints.up(768)]: {
      position: 'absolute',
      top: '0'
    }
  },
  indicators: {
    [theme.breakpoints.down(600)]: {
      display: 'none'
    },
    [theme.breakpoints.down(768)]: {
      flex: 'initial',
      marginLeft: theme.spacing(0.5),
      order: 2
    },
    [theme.breakpoints.up(768)]: {
      height: theme.spacing(2.5),
      marginLeft: theme.spacing(3.75)
    }
  },
  subMenu: {
    backgroundColor: theme.palette.background.default,
    boxShadow: theme.shadows[3],
    boxSizing: 'border-box',
    left: 0,
    position: 'absolute',
    textAlign: 'left',
    top: `calc(100% + ${theme.spacing(1.25)})`,
    visibility: 'hidden',
    width: theme.spacing(20),
    zIndex: theme.zIndex.mobileStepper
  },
  subMenuOpen: {
    visibility: 'visible'
  },
  textWrapper: {
    alignItems: 'center',
    display: 'inline-flex',
    flex: '100%',
    fontSize: theme.typography.body2.fontSize,
    lineHeight: '1',
    whiteSpace: 'nowrap',
    [theme.breakpoints.down(768)]: {
      display: 'none'
    }
  }
}));

interface ItemLayoutProps {
  Icon: JSX.Element;
  renderIndicators: () => JSX.Element;
  renderSubMenu: (params: { closeSubMenu: () => void }) => JSX.Element;
  showPendingBadge: boolean;
  testId: string;
  title: string;
}

const ItemLayout = ({
  Icon,
  title,
  testId,
  renderIndicators,
  renderSubMenu,
  showPendingBadge
}: ItemLayoutProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const [toggled, setToggled] = useState(false);

  useEffect(() => {
    const closeMenu = (): void => setToggled(false);

    if (toggled) {
      window.addEventListener('locationchange', closeMenu);
    }

    return (): void => {
      window.removeEventListener('locationchange', closeMenu);
    };
  }, [toggled]);

  return (
    <ClickAwayListener
      onClickAway={(): void => {
        if (!toggled) {
          return;
        }
        setToggled(!toggled);
      }}
    >
      <div className={classes.container} data-testid={`${testId}-container`}>
        <div className={classes.header}>
          <div
            className={classes.indicators}
            data-testid={`${testId}-indicators`}
          >
            {renderIndicators()}
          </div>
          <button
            className={classes.button}
            data-testid={`${testId}-button`}
            type="button"
            onClick={(): void => setToggled(!toggled)}
          >
            <span className={classes.iconWrapper}>
              <Badge
                anchorOrigin={{ horizontal: 'right', vertical: 'top' }}
                color="pending"
                invisible={!showPendingBadge}
                overlap="circular"
                variant="dot"
              >
                <Icon />
              </Badge>
            </span>
            <span className={classes.textWrapper}>{title}</span>
            {toggled ? <ExpandMoreIcon /> : <ExpandLessIcon />}
          </button>
        </div>
        <div
          className={cx(classes.subMenu, { [classes.subMenuOpen]: toggled })}
          data-testid={`${testId}-sub-menu`}
        >
          {renderSubMenu({ closeSubMenu: () => setToggled(false) })}
        </div>
      </div>
    </ClickAwayListener>
  );
};

export default ItemLayout;
