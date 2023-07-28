import { ReactElement } from 'react';
import { Group } from '@visx/group';
import { HostGroupResourceName } from '../Resource.resource';
import {
  Cloud as CloudIcon,
  Dns as DnsIcon,
  Router as RouterIcon,
  Security as SecurityIcon,
  Storage as StorageIcon,
  Token as TokenIcon
} from '@mui/icons-material';

type HostGroupTypeProps = {
  type: HostGroupResourceName | string;
  className?: string;
}

const hostGroupTypeIcons: Record<HostGroupResourceName, any> = {
  server: DnsIcon,
  cloud: CloudIcon,
  storage: StorageIcon,
  router: RouterIcon,
  firewall: SecurityIcon,
  other: TokenIcon
};

const HostGroupTypeIcon = ({
  type,
  className = ''
}: HostGroupTypeProps): ReactElement => {
  const Icon = hostGroupTypeIcons[type] ?? hostGroupTypeIcons.other;

  return (
    <Group className={['host-group-type-icon', className].join(' ')}>
      <Icon inheritViewBox={true}/>
    </Group>
  );

};

export { HostGroupTypeIcon };