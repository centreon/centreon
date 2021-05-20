import * as React from 'react';

import {
  ClickAwayListener,
  Paper,
  Popper,
  PopperPlacementType,
  useTheme,
} from '@material-ui/core';

import { IconButton } from '..';

interface Props {
  children: React.ReactNode;
  icon: JSX.Element;
  onClose?: () => void;
  onOpen?: () => void;
  popperPlacement?: PopperPlacementType;
  title?: string;
}

const PopoverMenu = ({
  icon,
  title,
  children,
  popperPlacement,
  onOpen,
  onClose,
}: Props): JSX.Element => {
  const theme = useTheme();
  const [anchorEl, setAnchorEl] = React.useState();

  const isOpen = Boolean(anchorEl);

  const close = (reason?): void => {
    const isClosedByInputClick = reason?.type === 'mousedown';

    if (isClosedByInputClick) {
      return;
    }
    setAnchorEl(undefined);
  };

  const toggle = (event): void => {
    if (isOpen) {
      close();
      onClose?.();
      return;
    }

    onOpen?.();
    setAnchorEl(event.currentTarget);
  };

  return (
    <ClickAwayListener onClickAway={close}>
      <div>
        <IconButton ariaLabel={title} title={title} onClick={toggle}>
          {icon}
        </IconButton>
        <Popper
          anchorEl={anchorEl}
          open={isOpen}
          placement={popperPlacement}
          style={{ zIndex: theme.zIndex.tooltip }}
        >
          <Paper>{children}</Paper>
        </Popper>
      </div>
    </ClickAwayListener>
  );
};

export default PopoverMenu;
