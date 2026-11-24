import { getHostFormPath, hostsPagePath } from '../Hosts/utils';
import { useIsPageAllowed } from '../useIsPageAllowed';
import { getHostConfigurationUrl } from './utils';

interface HostLink {
  // Within the React app, handled by the router.
  to?: string;
  // A legacy page, outside the router.
  href?: string;
}

// The form of a host: the React Hosts page when the user can reach it, the
// legacy form otherwise.
export const useHostLink = (id: number): HostLink => {
  const isHostsPageAllowed = useIsPageAllowed(hostsPagePath);

  return isHostsPageAllowed
    ? { to: getHostFormPath(id) }
    : { href: getHostConfigurationUrl(id) };
};
