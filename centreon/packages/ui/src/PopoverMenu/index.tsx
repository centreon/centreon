import {
  ClickAwayListener,
  Paper,
  Popper,
  type PopperPlacementType
} from '@mui/material';
import type { PopperProps } from '@mui/material/Popper';

import { type Dispatch, type SetStateAction, useEffect, useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import { IconButton } from '..';

const useStyles = makeStyles()((theme) => ({
  popover: {
    zIndex: theme.zIndex.tooltip
  },
  popoverIconButton: {
    padding: 0,
    width: '100%'
  }
}));

interface PopoverData {
  anchorEl: HTMLElement | undefined;
  setAnchorEl: Dispatch<SetStateAction<HTMLElement | undefined>>;
}

interface Props {
  canOpen?: boolean;
  children: ((props?: { close?: () => void }) => JSX.Element) | JSX.Element;
  className?: string;
  tooltipClassName?: string;
  dataTestId?: string;
  getPopoverData?: (data: PopoverData) => void;
  icon: JSX.Element;
  onClose?: () => void;
  onOpen?: () => void;
  popperPlacement?: PopperPlacementType;
  popperProps?: Partial<PopperProps>;
  title?: string;
}

const PopoverMenu = ({
  icon,
  title,
  children,
  popperPlacement,
  onOpen,
  onClose,
  canOpen = true,
  className,
  dataTestId,
  getPopoverData,
  tooltipClassName,
  popperProps
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const [anchorEl, setAnchorEl] = useState<HTMLElement | undefined>();
  const isOpen = Boolean(anchorEl);

  const close = (reason?: { type?: string }): void => {
    const isClosedByInputClick = reason?.type === 'mousedown';
    if (isClosedByInputClick) {
      return;
    }

    onClose?.();
    setAnchorEl(undefined);
  };

  const toggle = (event: React.MouseEvent<HTMLButtonElement>): void => {
    if (isOpen) {
      close();

      return;
    }

    onOpen?.();
    setAnchorEl(event.currentTarget);
  };

  useEffect(() => {
    if (!canOpen && isOpen) {
      close();
    }
  }, [canOpen, close, isOpen]);

  useEffect(() => {
    getPopoverData?.({ anchorEl, setAnchorEl });
  }, [anchorEl, getPopoverData]);

  return (
    <>
      <IconButton
        ariaLabel={title}
        className={cx(classes.popoverIconButton, className)}
        data-testid={dataTestId}
        onClick={toggle}
        size="large"
        title={title}
      >
        {icon}
      </IconButton>
      {isOpen && (
        <ClickAwayListener onClickAway={close}>
          <Popper
            anchorEl={anchorEl}
            className={classes.popover}
            open
            placement={popperPlacement}
            {...popperProps}
          >
            <Paper className={tooltipClassName}>
              {typeof children === 'function' ? children({ close }) : children}
            </Paper>
          </Popper>
        </ClickAwayListener>
      )}
    </>
  );
};

export default PopoverMenu;
