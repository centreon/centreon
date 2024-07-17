import { ReactNode, useEffect, useState } from 'react';

import { equals } from 'ramda';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { ClickAwayListener } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';

import { IconButton } from '@centreon/ui';

import IconArrow from './IconArrow';
import { useStyles } from './check.styles';
import { Arguments, ClickList, Params } from './models';

interface Props {
  disabledButton?: boolean;
  onClickActionButton: () => void;
  onClickList?: ClickList;
  renderCheckOptionList?: (args: Arguments) => ReactNode;
  renderResourceActionButton: (params: Params) => ReactNode;
}

const Check = ({
  disabledButton,
  onClickActionButton,
  renderResourceActionButton,
  renderCheckOptionList
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const displayList = (event: React.MouseEvent<HTMLElement>): void => {
    setAnchorEl(event.currentTarget);
  };

  const arrowIconId = 'arrowIcon';
  const isOpen = Boolean(anchorEl);

  const iconProps = {
    icon: <ArrowDropDownIcon fontSize="small" id={arrowIconId} />
  };

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
        className={cx(classes.buttonGroup, classes.condensed)}
        onClick={handleClick}
      >
        {renderResourceActionButton({ onClick: handleClickActionButton })}
        <IconButton
          ariaLabel="arrow"
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
