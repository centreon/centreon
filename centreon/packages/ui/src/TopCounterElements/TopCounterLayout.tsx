import { useState, useEffect } from 'react';

import { makeStyles } from 'tss-react/mui';

import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import { Badge, ClickAwayListener } from '@mui/material';
import type { SvgIcon } from '@mui/material';

import useCloseOnLegacyPage from './useCloseOnLegacyPage';

const useStyles = makeStyles()((theme) => ({
  button: {
    '& > svg': {
      height: '0.9em',
      margin: `-${theme.spacing(0.5)}`,
      [theme.breakpoints.down(1025)]: {
        margin: `-${theme.spacing(0.5)}`
      }
    },
    appearance: 'none',
    background: 'none',
    border: 0,
    color: theme.palette.common.white,
    cursor: 'pointer',
    display: 'flex',

    [theme.breakpoints.up(1025)]: {
      alignItems: 'center',
      flexFlow: 'row no-wrap',
      marginTop: theme.spacing(0.5)
    },

    [theme.breakpoints.down(1025)]: {
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
    [theme.breakpoints.down(1025)]: {
      display: 'flex',
      flexFlow: 'row no-wrap'
    }
  },
  iconWrapper: {
    [theme.breakpoints.up(1025)]: {
      position: 'absolute',
      top: '0'
    }
  },
  indicators: {
    lineHeight: 1,
    [theme.breakpoints.down(600)]: {
      display: 'none'
    },
    [theme.breakpoints.down(1025)]: {
      flex: 'initial',
      marginLeft: theme.spacing(0.5),
      order: 2
    },
    [theme.breakpoints.up(1025)]: {
      height: theme.spacing(2.5),
      marginLeft: theme.spacing(3.75)
    }
  },
  subMenu: {
    backgroundColor: theme.palette.background.default,
    boxShadow: theme.shadows[3],
    boxSizing: 'border-box',
    left: 0,
    minWidth: theme.spacing(20),
    position: 'absolute',
    textAlign: 'left',
    top: `calc(100% + ${theme.spacing(1.25)})`,
    visibility: 'hidden',
    zIndex: theme.zIndex.mobileStepper
  },
  subMenuOpen: {
    visibility: 'visible'
  },
  textWrapper: {
    alignItems: 'center',
    display: 'inline-flex',
    flex: '100%',
    fontSize: theme.typography.body1.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    lineHeight: '1',
    whiteSpace: 'nowrap',
    [theme.breakpoints.down(1025)]: {
      display: 'none'
    }
  }
}));

interface TopCounterLayoutProps {
  Icon: typeof SvgIcon;
  renderIndicators: () => JSX.Element;
  renderSubMenu: (params: { closeSubMenu: () => void }) => JSX.Element;
  showPendingBadge?: boolean;
  title: string;
}

const TopCounterLayout = ({
  Icon,
  title,
  renderIndicators,
  renderSubMenu,
  showPendingBadge
}: TopCounterLayoutProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const [toggled, setToggled] = useState(false);

  const subMenuId = title.replace(/[^A-Za-z]/, '-');
  useCloseOnLegacyPage({ setToggled });

  useEffect(() => {
    const closeMenu = (): void => setToggled(false);
    const closeOnEscape = (event: KeyboardEvent): void => {
      if (event.key === 'esc' || event.key === 'Escape') {
        event.preventDefault();
        closeMenu();
      }
    };

    if (toggled) {
      window.addEventListener('locationchange', closeMenu);
      window.addEventListener('keydown', closeOnEscape);
    }

    return (): void => {
      window.removeEventListener('locationchange', closeMenu);
      window.removeEventListener('keydown', closeOnEscape);
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
      <div className={classes.container}>
        <div className={classes.header}>
          <div className={classes.indicators}>{renderIndicators()}</div>
          <button
            aria-controls={`${subMenuId}-menu`}
            aria-expanded={toggled}
            aria-haspopup="true"
            aria-label={title}
            className={classes.button}
            id={`${subMenuId}-button`}
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
            {toggled ? <ExpandLessIcon /> : <ExpandMoreIcon />}
          </button>
        </div>
        <div
          aria-labelledby={`${subMenuId}-button`}
          className={cx(classes.subMenu, { [classes.subMenuOpen]: toggled })}
          id={`${subMenuId}-menu`}
          role="menu"
        >
          {renderSubMenu({ closeSubMenu: () => setToggled(false) })}
        </div>
      </div>
    </ClickAwayListener>
  );
};

export default TopCounterLayout;
