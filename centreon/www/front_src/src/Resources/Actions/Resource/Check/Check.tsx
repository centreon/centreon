import { useState } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconArrow from '@mui/icons-material/KeyboardArrowDownOutlined';
import { useMediaQuery, useTheme } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';

import { IconButton } from '@centreon/ui';

import ResourceActionButton from '../ResourceActionButton';
import useMediaQueryListing from '../useMediaQueryListing';

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
      minWidth: theme.spacing(16)
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
  const displayPopover = (event: React.MouseEvent<HTMLElement>): void => {
    setAnchorEl(event.currentTarget);
  };

  const idArrowIcon = 'arrowIcon';

  const closePopover = (): void => {
    setAnchorEl(null);
  };

  const { applyBreakPoint } = useMediaQueryListing();

  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1024))) || applyBreakPoint;

  const handleClick = (event): void => {
    if (!equals(event.target?.id, idArrowIcon)) {
      return;
    }
    if (!anchorEl) {
      displayPopover(event);

      return;
    }
    closePopover();
  };

  return (
    <>
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
          onClick={onClickActionButton}
        />
        <IconButton
          ariaLabel="arrow"
          className={cx({ [classes.iconArrow]: !displayCondensed })}
          disabled={disabledButton}
          id={idArrowIcon}
          onClick={(): void => undefined}
        >
          {displayCondensed ? (
            <ArrowDropDownIcon fontSize="small" id={idArrowIcon} />
          ) : (
            <IconArrow id={idArrowIcon} />
          )}
        </IconButton>
      </ButtonGroup>
      <CheckOptionsList
        anchorEl={anchorEl}
        disabled={disabledList}
        isDefaultChecked={isDefaultChecked}
        open={Boolean(anchorEl)}
        onClickCheck={onClickList?.onClickCheck}
        onClickForcedCheck={onClickList?.onClickForcedCheck}
      />
    </>
  );
};

export default Check;
