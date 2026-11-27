import { ListItemButton } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import type { ReactElement } from 'react';

import type { RouteHierarchy, SubRouteActions } from './models';
import { SubRouteContent } from './SubRoutes';
import { containsRoute, hasChildRoutes } from './utils';

interface Props {
  actions: SubRouteActions;
  entry: RouteHierarchy;
  isExpanded: boolean;
  isMenuOpen: boolean;
  isPopoverOpen: boolean;
  onNavigate: (route: string) => void;
  onToggleExpanded: () => void;
  onTogglePopover: (entry: RouteHierarchy, element: HTMLElement) => void;
}

/**
 * A top-level entry. Expanded menu: an inline accordion. Collapsed menu: an
 * icon that opens the entry popover, or navigates when the entry is a leaf.
 * Double-clicking a parent navigates to its route.
 */
const NavigationMenuItem = ({
  actions,
  entry,
  isExpanded,
  isMenuOpen,
  isPopoverOpen,
  onNavigate,
  onToggleExpanded,
  onTogglePopover
}: Props): ReactElement => {
  if (isMenuOpen) {
    return (
      <li>
        <SubRouteContent
          actions={actions}
          entry={entry}
          isExpanded={isExpanded}
          onToggle={onToggleExpanded}
          variant="root"
        />
      </li>
    );
  }

  const { Icon, label, route } = entry;
  const hasChildren = hasChildRoutes(entry);

  return (
    <li>
      <Tooltip
        followCursor={false}
        label={isPopoverOpen ? '' : label}
        position="right"
      >
        <ListItemButton
          aria-expanded={hasChildren ? isPopoverOpen : undefined}
          aria-label={label}
          className="group size-11 flex-none justify-center rounded-sm p-0 hover:bg-transparent"
          data-selected={containsRoute(entry, actions.selectedRoute)}
          onClick={(event): void =>
            hasChildren
              ? onTogglePopover(entry, event.currentTarget)
              : onNavigate(route)
          }
          onDoubleClick={
            hasChildren ? (): void => onNavigate(route) : undefined
          }
        >
          <span
            className={`flex size-7 items-center justify-center rounded-sm text-white transition-colors duration-[120ms] group-hover:bg-white/16 dark:group-hover:bg-white/8 ${isPopoverOpen ? 'bg-white/16 dark:bg-white/8' : ''}`}
          >
            {Icon ? (
              <Icon />
            ) : (
              <span className="text-sm font-bold">{label.charAt(0)}</span>
            )}
          </span>
        </ListItemButton>
      </Tooltip>
    </li>
  );
};

export default NavigationMenuItem;
