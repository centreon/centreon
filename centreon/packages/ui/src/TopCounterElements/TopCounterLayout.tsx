import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import type { SvgIcon } from '@mui/material';
import { ClickAwayListener, Tooltip } from '@mui/material';

import type { MouseEvent } from 'react';
import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { makeStyles } from 'tss-react/mui';

import useCloseOnLegacyPage from './useCloseOnLegacyPage';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    position: 'relative'
  },
  icon: {
    '& > svg': {
      display: 'block',
      height: '16px',
      width: '16px'
    },
    alignItems: 'center',
    color: 'inherit',
    display: 'inline-flex',
    textDecoration: 'none'
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
  },
  tile: {
    '& > svg': {
      height: '16px',
      width: '16px'
    },
    '&:hover': {
      borderColor: theme.palette.tile.borderHover
    },
    alignItems: 'center',
    appearance: 'none',
    background: theme.palette.tile.background,
    border: `1px solid ${theme.palette.tile.border}`,
    borderRadius: theme.spacing(1),
    color: theme.palette.text.primary,
    display: 'inline-flex',
    flexFlow: 'row nowrap',
    gap: theme.spacing(1),
    padding: '5px 9px'
  },
  tileButton: {
    cursor: 'pointer'
  }
}));

interface TopCounterLayoutProps {
  Icon: typeof SvgIcon;
  iconLink?: string;
  iconOnClick?: (e: MouseEvent<HTMLAnchorElement>) => void;
  renderIndicators: () => JSX.Element;
  renderSubMenu?: (params: { closeSubMenu: () => void }) => JSX.Element;
  title: string;
  tooltipDescription?: string;
}

const TopCounterLayout = ({
  Icon,
  title,
  iconLink,
  iconOnClick,
  renderIndicators,
  renderSubMenu,
  tooltipDescription
}: TopCounterLayoutProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const [toggled, setToggled] = useState(false);
  const subMenuId = title.replace(/[^A-Za-z]/, '-');
  const isExpandable = Boolean(renderSubMenu);
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

  const iconWithTooltip = (
    <Tooltip
      disableInteractive
      enterDelay={500}
      enterNextDelay={500}
      placement="bottom"
      title={tooltipDescription ?? title}
    >
      <Icon />
    </Tooltip>
  );

  if (!isExpandable) {
    return (
      <div className={classes.tile}>
        {iconLink ? (
          <Link
            aria-label={title}
            className={classes.icon}
            to={iconLink}
            onClick={iconOnClick}
          >
            {iconWithTooltip}
          </Link>
        ) : (
          <span className={classes.icon}>{iconWithTooltip}</span>
        )}
        <span className={classes.indicators}>{renderIndicators()}</span>
      </div>
    );
  }

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
          className={cx(classes.tile, classes.tileButton)}
          id={`${subMenuId}-button`}
          onClick={(): void => setToggled(!toggled)}
          type="button"
        >
          <span className={classes.icon}>{iconWithTooltip}</span>
          <span className={classes.indicators}>{renderIndicators()}</span>
          {toggled ? <ExpandLessIcon /> : <ExpandMoreIcon />}
        </button>
        <div
          aria-labelledby={`${subMenuId}-button`}
          className={cx(classes.subMenu, { [classes.subMenuOpen]: toggled })}
          id={`${subMenuId}-menu`}
          role="menu"
        >
          {renderSubMenu?.({ closeSubMenu: () => setToggled(false) })}
        </div>
      </div>
    </ClickAwayListener>
  );
};

export default TopCounterLayout;
