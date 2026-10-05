import { SidebarMenu } from './SidebarMenu';
import useNavigation from './useNavigation';

const Navigation = (): JSX.Element => {
  const { menu } = useNavigation();

  return <SidebarMenu navigationData={menu} />;
};

export default Navigation;
