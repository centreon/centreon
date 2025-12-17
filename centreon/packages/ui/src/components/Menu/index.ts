import { MenuButton } from './Button';
import { Menu as MenuRoot } from './Menu';
import { MenuDivider } from './MenuDivider';
import { MenuItem } from './MenuItem';
import { MenuItems } from './MenuItems';

export const Menu = Object.assign(MenuRoot, {
  Button: MenuButton,
  Divider: MenuDivider,
  Item: MenuItem,
  Items: MenuItems
});
