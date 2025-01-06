import { type Dispatch, type SetStateAction, useEffect, useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import {
  ClickAwayListener,
  Paper,
  Popper,
  type PopperPlacementType
} from '@mui/material';
import type { PopperProps } from '@mui/material/Popper';

import { equals, type } from 'ramda';
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
  children: (props?) => JSX.Element | JSX.Element;
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

  const close = (reason?): void => {
    const isClosedByInputClick = reason?.type === 'mousedown';
    if (isClosedByInputClick) {
      return;
    }

    onClose?.();
    setAnchorEl(undefined);
  };

  const toggle = (event): void => {
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
  }, [canOpen]);

  useEffect(() => {
    getPopoverData?.({ anchorEl, setAnchorEl });
  }, [anchorEl]);

  return (
    <>
      <IconButton
        ariaLabel={title}
        className={cx(classes.popoverIconButton, className)}
        data-testid={dataTestId}
        size="large"
        title={title}
        onClick={toggle}
      >
        {icon}
      </IconButton>
      {isOpen && (
        <ClickAwayListener onClickAway={close}>
          <Popper
            open
            anchorEl={anchorEl}
            className={classes.popover}
            nonce={undefined}
            placement={popperPlacement}
            onResize={(): undefined => undefined}
            onResizeCapture={(): undefined => undefined}
            {...popperProps}
          >
            <Paper className={tooltipClassName}>
              {equals(type(children), 'Function')
                ? children({ close })
                : children}
            </Paper>
          </Popper>
        </ClickAwayListener>
      )}
    </>
  );
};

export default PopoverMenu;
