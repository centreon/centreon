import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import type { SvgIcon } from '@mui/material';
import { Badge, ClickAwayListener, Tooltip } from '@mui/material';

import { useEffect, useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import useCloseOnLegacyPage from './useCloseOnLegacyPage';

const useStyles = makeStyles()((theme) => ({
  button: {
    '& > svg': {
      height: '1.15em'
    },
    alignItems: 'center',
    appearance: 'none',
    background: 'none',
    border: 0,
    color: theme.palette.common.white,
    cursor: 'pointer',
    display: 'inline-flex',
    flexFlow: 'row nowrap',
    gap: theme.spacing(1),
    padding: 0
  },
  container: {
    alignItems: 'center',
    display: 'flex',
    position: 'relative'
  },
  indicators: {
    alignItems: 'center',
    display: 'inline-flex',
    lineHeight: 1,
    [theme.breakpoints.down(600)]: {
      display: 'none'
    }
  },
  subMenu: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.spacing(1),
    boxShadow: theme.shadows[3],
    boxSizing: 'border-box',
    left: 0,
    minWidth: theme.spacing(20),
    overflow: 'hidden',
    position: 'absolute',
    textAlign: 'left',
    top: `calc(100% + ${theme.spacing(1.25)})`,
    visibility: 'hidden',
    zIndex: theme.zIndex.mobileStepper
  },
  subMenuOpen: {
    visibility: 'visible'
  }
}));

interface TopCounterLayoutProps {
  Icon: typeof SvgIcon;
  renderIndicators: () => JSX.Element;
  renderSubMenu: (params: { closeSubMenu: () => void }) => JSX.Element;
  showPendingBadge?: boolean;
  title: string;
  tooltipDescription?: string;
}

const TopCounterLayout = ({
  Icon,
  title,
  renderIndicators,
  renderSubMenu,
  showPendingBadge,
  tooltipDescription
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
        <button
          aria-controls={`${subMenuId}-menu`}
          aria-expanded={toggled}
          aria-haspopup="true"
          aria-label={title}
          className={classes.button}
          id={`${subMenuId}-button`}
          onClick={(): void => setToggled(!toggled)}
          type="button"
        >
          <Tooltip
            disableInteractive
            enterDelay={500}
            enterNextDelay={500}
            placement="bottom"
            title={tooltipDescription ?? title}
          >
            <Badge
              anchorOrigin={{ horizontal: 'right', vertical: 'top' }}
              color="pending"
              invisible={!showPendingBadge}
              overlap="circular"
              variant="dot"
            >
              <Icon />
            </Badge>
          </Tooltip>
          <span className={classes.indicators}>{renderIndicators()}</span>
          {toggled ? <ExpandLessIcon /> : <ExpandMoreIcon />}
        </button>
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
