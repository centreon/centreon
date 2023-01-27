import * as React from 'react';

import { equals } from 'ramda';
import { Link } from 'react-router-dom';
import { makeStyles } from 'tss-react/mui';

import { ClickAwayListener } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import IconHeader from '../../Icon/IconHeader';
import StatusCounter from '../../StatusCounter';
import { SeverityCode } from '../../StatusChip';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

import SubmenuItems from './SubmenuItems';
import SubmenuItem from './SubmenuItem';

interface StyleProps {
  counterRightTranslation?: number;
}

const useStyles = makeStyles<StyleProps>()((theme) => ({
  active: {
    backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
      ? theme.palette.common.black
      : theme.palette.primary.dark
  },
  bottom: {
    display: 'flex'
  },
  link: {
    textDecoration: 'none'
  },
  subMenuCounters: {
    display: 'flex',
    gap: theme.spacing(0.5)
  },
  subMenuRight: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between'
  },
  submenu: {
    backgroundColor: theme.palette.background.default,
    boxShadow: theme.shadows[3],
    boxSizing: 'border-box',
    display: 'none',
    left: 0,
    position: 'absolute',
    textAlign: 'left',
    top: 'calc(100% + 10px)',
    width: theme.spacing(18),
    zIndex: theme.zIndex.mobileStepper
  },
  submenuDisplayed: {
    display: 'block'
  },
  top: {
    display: 'flex',
    position: 'relative'
  },
  wrapMiddleIcon: {
    color: 'inherit',
    fontSize: 0
  }
}));

interface IconHeaderProps {
  Icon: (props) => JSX.Element;
  iconName: string;
  onClick: () => void;
}

interface CounterProps {
  count: number;
  onClick: (e: React.MouseEvent) => void;
  severityCode: number;
  testId?: string;
  to: string;
}

interface IconToggleSubmenuProps {
  Label?: string;
  onClick: () => void;
  rotate: boolean;
  testid?: string;
}

interface SubmenuItemProps {
  countTestId?: string;
  onClick: (e: React.MouseEvent) => void;
  severityCode?: SeverityCode;
  submenuCount: string | number;
  submenuTitle: string;
  titleTestId?: string;
  to: string;
}

export interface Props {
  active: boolean;
  counterRightTranslation?: number;
  counters: Array<CounterProps>;
  hasPending?: boolean;
  iconHeader: IconHeaderProps;
  iconToggleSubmenu: IconToggleSubmenuProps;
  submenuItems: Array<SubmenuItemProps>;
  toggled?: boolean;
}

const SubmenuHeader = ({
  active,
  iconHeader,
  counters,
  iconToggleSubmenu,
  submenuItems,
  toggled,
  hasPending,
  counterRightTranslation,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ counterRightTranslation });

  return (
    <ClickAwayListener
      onClickAway={(): void => {
        if (!toggled) {
          return;
        }
        iconHeader.onClick();
      }}
    >
      <div
        className={cx(classes.top, {
          [classes.active]: active
        })}
        {...props}
      >
        {iconHeader && (
          <IconHeader
            Icon={iconHeader.Icon}
            iconName={iconHeader.iconName}
            pending={hasPending}
            onClick={iconHeader.onClick}
          />
        )}

        <div className={classes.subMenuRight}>
          <div className={classes.subMenuCounters}>
            {counters?.map(({ testId, to, onClick, count, severityCode }) => {
              return (
                <Link
                  className={cx(classes.link, classes.wrapMiddleIcon)}
                  data-testid={testId}
                  key={to}
                  to={to}
                  onClick={onClick}
                >
                  <StatusCounter count={count} severityCode={severityCode} />
                </Link>
              );
            })}
          </div>
          <IconToggleSubmenu
            aria-label={iconToggleSubmenu?.Label}
            data-testid={iconToggleSubmenu?.testid}
            rotate={iconToggleSubmenu?.rotate}
            onClick={iconToggleSubmenu?.onClick}
          />
        </div>

        <div
          className={cx(classes.submenu, {
            [classes.submenuDisplayed]: toggled
          })}
        >
          <SubmenuItems>
            {submenuItems?.map(
              ({
                to,
                onClick,
                countTestId,
                severityCode,
                submenuCount,
                submenuTitle,
                titleTestId
              }) => {
                return (
                  <SubmenuItem
                    countTestId={countTestId}
                    key={to}
                    severityCode={severityCode as SeverityCode}
                    submenuCount={submenuCount}
                    submenuTitle={submenuTitle}
                    titleTestId={titleTestId}
                    to={to}
                    onClick={onClick}
                  />
                );
              }
            )}
          </SubmenuItems>
        </div>
      </div>
    </ClickAwayListener>
  );
};

export default SubmenuHeader;
