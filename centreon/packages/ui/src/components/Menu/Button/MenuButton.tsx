import { ReactElement, ReactNode, useCallback } from 'react';

import {
  ArrowDropDown as ArrowDropDownIcon,
  Menu as MenuIcon
} from '@mui/icons-material';

import { AriaLabelingAttributes } from '../../../@types/aria-attributes';
import { DataTestAttributes } from '../../../@types/data-attributes';
import { Button, ButtonProps } from '../../Button';
import { useMenu } from '../useMenu';

import { useStyles } from './MenuButton.styles';

type MenuButtonProps = {
  ariaLabel?: string;
  children?: ReactNode;
  className?: string;
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
  ariaLabel,
  className,
  ...attr
}: MenuButtonProps): ReactElement => {
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
      aria-label={ariaLabel}
      className={`${isMenuOpen && 'bg-primary-main/8'} ${isMenuOpen ? 'text-text-primary-main' : 'text-text-secondary'} ${className}`}
      data-is-active={isMenuOpen}
      size={size}
      variant={variant}
      onClick={onToggle}
    >
      {children || <MenuIcon />}
      {hasArrow && (
        <ArrowDropDownIcon
          className={`transform-gpu transition-[rotate] ${isMenuOpen ? 'rotate-180' : 'rotate-0'}`}
        />
      )}
    </Button>
  );
};

export { MenuButton };
