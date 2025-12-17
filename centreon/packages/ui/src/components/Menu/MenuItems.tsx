import { Menu as MuiMenu } from "@mui/material";
import type { ReactElement, ReactNode } from "react";

import { useMenu } from "./useMenu";

type MenuItemsProps = {
	children?: ReactNode | Array<ReactNode>;
	className?: string;
};
const MenuItems = ({ children, className }: MenuItemsProps): ReactElement => {
	const { isMenuOpen, setIsMenuOpen, anchorEl, onClose } = useMenu();

	const onCloseMenu = (): void => {
		setIsMenuOpen(false);
		onClose?.();
	};

	return (
		<MuiMenu
			anchorEl={anchorEl}
			className={className}
			classes={{
				paper:
					"rounded-sm shadow-lg min-w-[240px] bg-background-paper border-1 border-divider",
			}}
			open={isMenuOpen}
			variant="menu"
			onClick={onCloseMenu}
			onClose={onCloseMenu}
		>
			{children}
		</MuiMenu>
	);
};

export { MenuItems };
