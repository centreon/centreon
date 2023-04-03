import { useState, useEffect } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconArrowUp from '@mui/icons-material/KeyboardArrowUp';
import IconArrowDown from '@mui/icons-material/KeyboardArrowDownOutlined';
import { ClickAwayListener, useMediaQuery, useTheme } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import ArrowDropUpIcon from '@mui/icons-material/ArrowDropUp';

import { IconButton } from '@centreon/ui';

import ResourceActionButton from '../ResourceActionButton';
import useMediaQueryListing from '../useMediaQueryListing';

import IconArrow from './IconArrow';
import CheckOptionsList from './CheckOptionsList';

const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center'
  },
  condensed: {
    marginRight: theme.spacing(2)
  },
  container: {
    '& .MuiButton-root': {
      backgroundColor: 'transparent',
      boxShadow: theme.spacing(0, 0)
    },
    backgroundColor: theme.palette.primary.main
  },
  disabled: {
    backgroundColor: theme.palette.action.disabledBackground
  },
  iconArrow: {
    color: theme.palette.background.paper
  }
}));

const defaultDisabledList = { disableCheck: false, disableForcedCheck: false };

interface ClickList {
  onClickCheck: () => void;
  onClickForcedCheck: () => void;
}
interface Disabled {
  disableCheck: boolean;
  disableForcedCheck: boolean;
}

interface Props {
  disabledButton: boolean;
  disabledList?: Disabled;
  icon: JSX.Element;
  isActionPermitted: boolean;
  isDefaultChecked?: boolean;
  labelButton: string;
  onClickActionButton: () => void;
  onClickList?: ClickList;
  testId: string;
}

const Check = ({
  disabledButton,
  disabledList = defaultDisabledList,
  labelButton,
  isActionPermitted,
  testId,
  onClickList,
  onClickActionButton,
  icon,
  isDefaultChecked = false
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const theme = useTheme();
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const displayList = (event: React.MouseEvent<HTMLElement>): void => {
    setAnchorEl(event.currentTarget);
  };

  const arrowIconId = 'arrowIcon';
  const isOpen = Boolean(anchorEl);

  const closeList = (): void => {
    setAnchorEl(null);
  };

  const { applyBreakPoint } = useMediaQueryListing();

  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1024))) || applyBreakPoint;

  const handleClick = (event): void => {
    const { target } = event;
    if (
      !equals(target?.id, arrowIconId) &&
      !equals(target?.parentElement?.id, arrowIconId)
    ) {
      return;
    }
    if (!anchorEl) {
      displayList(event);

      return;
    }
    closeList();
  };

  const handleClickAway = (): void => {
    if (!anchorEl) {
      return;
    }
    closeList();
  };

  const handleClickActionButton = (): void => {
    onClickActionButton();
    if (!isOpen) {
      return;
    }
    closeList();
  };

  useEffect(() => {
    if (!disabledButton) {
      return;
    }
    setAnchorEl(null);
  }, [disabledButton]);

  return (
    <ClickAwayListener onClickAway={handleClickAway}>
      <ButtonGroup
        className={cx(classes.buttonGroup, {
          [classes.container]: !displayCondensed,
          [classes.disabled]: disabledButton && !displayCondensed,
          [classes.condensed]: displayCondensed
        })}
        onClick={handleClick}
      >
        <ResourceActionButton
          disabled={disabledButton}
          icon={icon}
          label={t(labelButton)}
          permitted={isActionPermitted}
          testId={testId}
          onClick={handleClickActionButton}
        />
        <IconButton
          ariaLabel="arrow"
          className={cx({ [classes.iconArrow]: !displayCondensed })}
          disabled={disabledButton}
          id={arrowIconId}
          onClick={(): void => undefined}
        >
          {displayCondensed ? (
            <IconArrow
              iconDown={<ArrowDropDownIcon fontSize="small" id={arrowIconId} />}
              iconUp={<ArrowDropUpIcon fontSize="small" id={arrowIconId} />}
              open={isOpen}
            />
          ) : (
            <IconArrow
              iconDown={<IconArrowDown id={arrowIconId} />}
              iconUp={<IconArrowUp id={arrowIconId} />}
              open={isOpen}
            />
          )}
        </IconButton>
        <CheckOptionsList
          anchorEl={anchorEl}
          disabled={disabledList}
          isDefaultChecked={isDefaultChecked}
          open={isOpen}
          onClickCheck={onClickList?.onClickCheck}
          onClickForcedCheck={onClickList?.onClickForcedCheck}
        />
      </ButtonGroup>
    </ClickAwayListener>
  );
};

export default Check;
