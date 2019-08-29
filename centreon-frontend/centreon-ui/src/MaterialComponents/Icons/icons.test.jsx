/* eslint-disable no-undef */

import React from 'react';
import { render } from '@testing-library/react';
import IconAttach from './IconAttach';
import IconClose from './IconClose';
import IconEdit from './IconEdit';
import IconDelete from './IconDelete';
import IconInsertChart from './IconInsertChart';
import IconLibraryAdd from './IconLibraryAdd';
import IconPowerSettings from './IconPowerSettings';
import IconPowerSettingsDisable from './IconPowerSettingsDisable';
import IconVisible from './IconVisible';
import IconInvisible from './IconInvisible';

[
  IconAttach,
  IconClose,
  IconEdit,
  IconDelete,
  IconInsertChart,
  IconLibraryAdd,
  IconPowerSettings,
  IconPowerSettingsDisable,
  IconVisible,
  IconInvisible,
].forEach((IconComponent) => {
  describe(IconComponent, () => {
    it('renders', () => {
      const { container } = render(<IconComponent />);

      expect(container.firstChild).toMatchSnapshot();
    });
  });
});
