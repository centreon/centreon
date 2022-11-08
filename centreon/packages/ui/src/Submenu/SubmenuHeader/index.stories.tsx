import React from 'react';

import HostIcon from '@material-ui/icons/Dns';

import { IconHeader, StatusCounter, IconToggleSubmenu } from '../..';
import { SeverityCode } from '../../StatusChip';

import SubmenuItems from './SubmenuItems';
import SubmenuItem from './SubmenuItem';

import SubmenuHeader from '.';

export default { title: 'SubemnuHeader' };

const Submenu = (): JSX.Element => {
  const [active, setActive] = React.useState(false);

  return (
    <div style={{ width: 'fit-content' }}>
      <SubmenuHeader active={active}>
        <IconHeader
          pending
          Icon={HostIcon}
          iconName="Hosts"
          onClick={(): void => setActive(!active)}
        />
        <StatusCounter count={1} severityCode={SeverityCode.High} />
        <StatusCounter count={0} severityCode={SeverityCode.Low} />
        <StatusCounter count={200} severityCode={SeverityCode.Ok} />
        <IconToggleSubmenu
          rotate={false}
          onClick={(): void => setActive(!active)}
        />
        <div
          style={{
            backgroundColor: '#232f39',
            boxSizing: 'border-box',
            display: active ? 'block' : 'none',
            left: 0,
            padding: '16px',
            position: 'absolute',
            textAlign: 'left',
            top: '100%',
            width: '100%',
            zIndex: 99,
          }}
        >
          <SubmenuItems>
            <SubmenuItem submenuCount={6} submenuTitle="All" />
            <SubmenuItem
              dotColored="red"
              submenuCount="1/6"
              submenuTitle="Down"
            />
            <SubmenuItem
              dotColored="gray"
              submenuCount="2/6"
              submenuTitle="Unreachable"
            />
            <SubmenuItem
              dotColored="green"
              submenuCount={3}
              submenuTitle="Up"
            />
            <SubmenuItem
              dotColored="blue"
              submenuCount={0}
              submenuTitle="Pending"
            />
          </SubmenuItems>
        </div>
      </SubmenuHeader>
    </div>
  );
};

export const hostSubmenu = (): JSX.Element => <Submenu />;
