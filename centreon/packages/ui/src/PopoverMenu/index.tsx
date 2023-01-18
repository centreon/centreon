import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import {
  ClickAwayListener,
  Paper,
  Popper,
  PopperPlacementType,
  useTheme
} from '@mui/material';

import { IconButton } from '..';

const useStyles = makeStyles()(() => ({
  popoverIconButton: {
    padding: 0,
    width: '100%'
  }
}));

interface Props {
  children: (props?) => JSX.Element;
  className?: string;
  dataTestId?: string;
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
  className,
  dataTestId
}: Props): JSX.Element => {
  const theme = useTheme();
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
            nonce={undefined}
            placement={popperPlacement}
            style={{ zIndex: theme.zIndex.tooltip }}
            onResize={(): undefined => undefined}
            onResizeCapture={(): undefined => undefined}
          >
            <Paper>{children({ close })}</Paper>
          </Popper>
        </ClickAwayListener>
      )}
    </>
  );
};

export default PopoverMenu;
