import { MemoryRouter } from 'react-router';

import SideBar from './index';
import menuData from './tests/menuData.json';
import menuDataWithAdditionalLabel from './tests/menuDataWithAdditionalLabel.json';

// Default store: the theme follows the emulated color scheme of the gallery.
export const Default = (): JSX.Element => (
  <MemoryRouter>
    <SideBar navigationData={menuData.result as never} />
  </MemoryRouter>
);

export const WithAdditionalLabel = (): JSX.Element => (
  <MemoryRouter>
    <SideBar navigationData={menuDataWithAdditionalLabel.result as never} />
  </MemoryRouter>
);
