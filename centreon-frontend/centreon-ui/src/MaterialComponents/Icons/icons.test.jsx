/* eslint-disable no-undef */

import React from 'react';
import { render } from '@testing-library/react';
import IconAccessTime from './IconAccessTime';
import IconAttach from './IconAttach';
import IconClose from './IconClose';
import IconDone from './IconDone';
import IconEdit from './IconEdit';
import IconDelete from './IconDelete';
import IconInsertChart from './IconInsertChart';
import IconLibraryAdd from './IconLibraryAdd';
import IconPowerSettings from './IconPowerSettings';
import IconPowerSettingsDisable from './IconPowerSettingsDisable';
import IconRefresh from './IconRefresh';
import IconReportProblem from './IconReportProblem';
import IconVisible from './IconVisible';
import IconInvisible from './IconInvisible';

[
  IconAccessTime,
  IconAttach,
  IconClose,
  IconDone,
  IconEdit,
  IconDelete,
  IconInsertChart,
  IconLibraryAdd,
  IconPowerSettings,
  IconPowerSettingsDisable,
  IconRefresh,
  IconReportProblem,
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
