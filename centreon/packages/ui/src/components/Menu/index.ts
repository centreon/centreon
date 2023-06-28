import { Menu as MenuRoot } from './Menu';
import { MenuButton } from './Button';
import { MenuItem } from './MenuItem';
import { MenuDivider } from './MenuDivider';
import { MenuItems } from './MenuItems';

export const Menu = Object.assign(MenuRoot, {
  Button: MenuButton,
  Divider: MenuDivider,
  Item: MenuItem,
  Items: MenuItems
});
