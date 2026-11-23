import type { SvgIconProps } from '@mui/material';

import type { ComponentType, ElementType } from 'react';

/**
 * A navigable entry of the menu. A leaf has neither `subRoutes` nor `groups`;
 * a parent has one or the other.
 */
export interface RouteHierarchy {
  /** Small chip displayed next to the label (BETA, NEW…). */
  badge?: string;
  /** Child entries displayed under headed sections (MANAGEMENT, OPTIONS…). */
  groups?: Array<RouteGroup>;
  /** Displayed in the collapsed rail, top-level entries only. */
  Icon?: ComponentType<SvgIconProps>;
  label: string;
  /** Pages that belong to this entry without being displayed in the menu. */
  nonDisplayedRoutes?: Array<string>;
  /** Where the entry leads. For a parent, usually its first leaf. */
  route: string;
  subRoutes?: Array<RouteHierarchy>;
}

export interface RouteGroup {
  /** No label: the entries are displayed without a section header. */
  label?: string;
  subRoutes: Array<RouteHierarchy>;
}

export interface NavigationMenuLabels {
  collapse: string;
  expand: string;
  navigation: string;
}

export interface SubRouteActions {
  /** Router link rendered by leaves, receiving the route in its `to` prop. */
  LinkComponent?: ElementType;
  onSelect: (route: string) => void;
  selectedRoute?: string;
}
