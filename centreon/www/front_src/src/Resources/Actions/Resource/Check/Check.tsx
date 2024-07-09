import { ReactNode, useEffect, useState } from 'react';

import { equals } from 'ramda';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import IconArrowDown from '@mui/icons-material/KeyboardArrowDownOutlined';
import { ClickAwayListener } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';

import { IconButton } from '@centreon/ui';

import IconArrow from './IconArrow';
import { useStyles } from './check.styles';
import { Arguments, ClickList, Params } from './models';

interface Props {
  disabledButton?: boolean;
  displayCondensed?: boolean;
  onClickActionButton: () => void;
  onClickList?: ClickList;
  renderCheckOptionList?: (args: Arguments) => ReactNode;
  renderResourceActionButton: (params: Params) => ReactNode;
}

const Check = ({
  disabledButton,
  onClickActionButton,
  renderResourceActionButton,
  displayCondensed = false,
  renderCheckOptionList
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const displayList = (event: React.MouseEvent<HTMLElement>): void => {
    setAnchorEl(event.currentTarget);
  };

  const arrowIconId = 'arrowIcon';
  const isOpen = Boolean(anchorEl);

  const iconProps = displayCondensed
    ? {
        icon: <ArrowDropDownIcon fontSize="small" id={arrowIconId} />
      }
    : { icon: <IconArrowDown id={arrowIconId} /> };

  const closeList = (): void => {
    setAnchorEl(null);
  };

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
          [classes.disabled]: disabledButton && !displayCondensed
        })}
        onClick={handleClick}
      >
        {renderResourceActionButton({ onClick: handleClickActionButton })}
        <IconButton
          ariaLabel="arrow"
          className={cx({ [classes.iconArrow]: !displayCondensed })}
          disabled={disabledButton}
          id={arrowIconId}
          onClick={(): void => undefined}
        >
          <IconArrow {...iconProps} open={isOpen} />
        </IconButton>
        {renderCheckOptionList?.({
          anchorEl,
          isOpen
        })}
      </ButtonGroup>
    </ClickAwayListener>
  );
};

export default Check;
