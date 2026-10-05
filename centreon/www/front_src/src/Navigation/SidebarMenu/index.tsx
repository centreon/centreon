import { Page } from '../models';
import { Menu } from './Menu';
import { MenuLogo } from './MenuLogo';

interface SidebarMenuProps {
  navigationData?: Array<Page>;
}

export const SidebarMenu = ({
  navigationData
}: SidebarMenuProps): JSX.Element => (
  <div className="flex h-full shrink-0 flex-col bg-background-paper pb-2 pl-2">
    <MenuLogo />
    <div className="min-h-0 flex-1">
      <Menu navigationData={navigationData} />
    </div>
  </div>
);
