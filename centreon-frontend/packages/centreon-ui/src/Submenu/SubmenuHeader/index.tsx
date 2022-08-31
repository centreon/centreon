import * as React from 'react';

import clsx from 'clsx';
import { equals } from 'ramda';
import { Link } from 'react-router-dom';

import makeStyles from '@mui/styles/makeStyles';
import { Theme } from '@mui/material';
import { CreateCSSProperties } from '@mui/styles';

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

const useStyles = makeStyles<Theme, StyleProps>((theme) => {
  return {
    active: {
      backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.background.default
        : theme.palette.primary.dark,
    },
    bottom: {
      display: 'flex',
    },
    link: {
      textDecoration: 'none',
    },
    subMenuCounters: {
      display: 'flex',
      gap: theme.spacing(2.5),
      [theme.breakpoints.down(768)]: {
        display: 'grid',
        gridTemplateColumns: 'auto auto',
      },
    },
    subMenuRight: {
      alignItem: 'flex-start',
      display: 'flex',
      flexDirection: 'column',
      gap: theme.spacing(1),
      justifyContent: 'space-between',
      [theme.breakpoints.down(768)]: {
        alignItems: 'center',
        flexDirection: 'row',
      },
    },
    submenu: {
      backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.background.default
        : theme.palette.primary.main,
      boxSizing: 'border-box',
      display: 'none',
      left: 0,
      padding: `${theme.spacing(1)} ${theme.spacing(1)} 0 ${theme.spacing(1)}`,
      position: 'absolute',
      textAlign: 'left',
      top: '100%',
      width: theme.spacing(18),
      zIndex: theme.zIndex.mobileStepper,
    },
    submenuDisplayed: {
      display: 'block',
    },
    top: {
      backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.background.default
        : theme.palette.primary.main,
      display: 'flex',
      position: 'relative',
    },

    translateCountersNearToIcon: ({
      counterRightTranslation,
    }): CreateCSSProperties => ({
      position: 'relative',
      right: theme.spacing(counterRightTranslation || 0),
      [theme.breakpoints.down(768)]: {
        position: 'static',
      },
    }),

    wrapMiddleIcon: {
      display: 'flex',
    },
  };
});

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
  const classes = useStyles({ counterRightTranslation });

  return (
    <div
      className={clsx(classes.top, {
        [classes.active]: active,
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
        <div
          className={`${classes.subMenuCounters} ${classes.translateCountersNearToIcon}`}
        >
          {counters?.map(({ testId, to, onClick, count, severityCode }) => {
            return (
              <Link
                className={clsx(classes.link, classes.wrapMiddleIcon)}
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
        className={clsx({
          [classes.submenuDisplayed]: toggled,
          [classes.submenu]: true,
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
              titleTestId,
            }) => {
              return (
                <Link
                  className={classes.link}
                  key={to}
                  to={to}
                  onClick={onClick}
                >
                  <SubmenuItem
                    countTestId={countTestId}
                    severityCode={severityCode as SeverityCode}
                    submenuCount={submenuCount}
                    submenuTitle={submenuTitle}
                    titleTestId={titleTestId}
                  />
                </Link>
              );
            },
          )}
        </SubmenuItems>
      </div>
    </div>
  );
};

export default SubmenuHeader;
