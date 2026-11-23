import { ListItemButton } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import type { ReactElement } from 'react';

import type { RouteHierarchy, SubRouteActions } from './models';
import { SubRouteContent } from './SubRoutes';
import { containsRoute, hasChildRoutes } from './utils';

interface Props {
  actions: SubRouteActions;
  entry: RouteHierarchy;
  isMenuOpen: boolean;
  isPopoverOpen: boolean;
  onNavigate: (route: string) => void;
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
  isMenuOpen,
  isPopoverOpen,
  onNavigate,
  onTogglePopover
}: Props): ReactElement => {
  if (isMenuOpen) {
    return (
      <li>
        <SubRouteContent actions={actions} entry={entry} withIcon />
      </li>
    );
  }

  const { Icon, label, route } = entry;
  const hasChildren = hasChildRoutes(entry);
  const isHighlighted =
    isPopoverOpen || containsRoute(entry, actions.selectedRoute);

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
          className="justify-center rounded-sm px-0 py-1"
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
            className={`flex rounded-sm p-1 ${isHighlighted ? 'bg-white/40' : ''}`}
          >
            {Icon ? (
              <Icon className="text-white" />
            ) : (
              <span className="flex size-6 items-center justify-center font-bold">
                {label.charAt(0)}
              </span>
            )}
          </span>
        </ListItemButton>
      </Tooltip>
    </li>
  );
};

export default NavigationMenuItem;
