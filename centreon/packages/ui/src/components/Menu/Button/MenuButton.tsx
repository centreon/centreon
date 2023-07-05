import { ReactElement, ReactNode, useCallback } from 'react';

import {
  ArrowDropDown as ArrowDropDownIcon,
  Menu as MenuIcon
} from '@mui/icons-material';

import { Button, ButtonProps } from '../../Button';
import { AriaLabelingAttributes } from '../../../@types/aria-attributes';
import { DataTestAttributes } from '../../../@types/data-attributes';
import { useMenu } from '../useMenu';

import { useStyles } from './MenuButton.styles';

type MenuButtonProps = {
  children?: ReactNode;
  hasArrow?: boolean;
  isOpen?: boolean;
  onClick?: (args: { isOpen: boolean }) => void;
} & Pick<ButtonProps, 'disabled' | 'size' | 'variant'> &
  AriaLabelingAttributes &
  DataTestAttributes;

const MenuButton = ({
  children,
  onClick,
  hasArrow = true,
  size = 'small',
  variant = 'ghost',
  ...attr
}: MenuButtonProps): ReactElement => {
  const { classes } = useStyles();

  const { isMenuOpen, setIsMenuOpen, setAnchorEl, onOpen } = useMenu();

  const onToggle = useCallback(
    (e): void => {
      setAnchorEl(e.currentTarget);

      setIsMenuOpen(!isMenuOpen);
      onClick?.({ isOpen: !isMenuOpen });
      if (!isMenuOpen) onOpen?.();
    },
    [isMenuOpen, onClick, onOpen]
  );

  return (
    <Button
      {...attr}
      className={classes.menuButton}
      data-is-active={isMenuOpen}
      size={size}
      variant={variant}
      onClick={onToggle}
    >
      {children || <MenuIcon />}
      {hasArrow && <ArrowDropDownIcon className={classes.buttonIcon} />}
    </Button>
  );
};

export { MenuButton };
