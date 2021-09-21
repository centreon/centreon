import React from 'react';

import HostIcon from '@material-ui/icons/Dns';

import { IconHeader, IconNumber, IconToggleSubmenu } from '../..';

import SubmenuItems from './SubmenuItems';
import SubmenuItem from './SubmenuItem';

import SubmenuHeader from '.';

export default { title: 'SubemnuHeader' };

interface Props {
  iconType: string;
}

const Submenu = ({ iconType }: Props): JSX.Element => {
  const [active, setActive] = React.useState(false);

  return (
    <div style={{ width: 'fit-content' }}>
      <SubmenuHeader active={active} submenuType="top">
        <IconHeader
          pending
          Icon={HostIcon}
          iconName="Hosts"
          onClick={(): void => setActive(!active)}
        />
        <IconNumber
          iconColor="red"
          iconNumber={<span>1</span>}
          iconType={iconType}
        />
        <IconNumber
          iconColor="gray-dark"
          iconNumber={<span>2</span>}
          iconType={iconType}
        />
        <IconNumber
          iconColor="green"
          iconNumber={<span>3</span>}
          iconType={iconType}
        />
        <IconToggleSubmenu
          iconType="arrow"
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

export const hostSubmenu = (): JSX.Element => <Submenu iconType="bordered" />;

export const hostSubmenuWithColor = (): JSX.Element => (
  <Submenu iconType="colored" />
);
