import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Chip,
  ListItemButton
} from '@mui/material';

import type { ReactElement } from 'react';

import type { RouteHierarchy, SubRouteActions } from './models';
import { useExpandedEntry } from './useExpandedEntry';
import {
  containsRoute,
  getChildRoutes,
  getEntryKey,
  hasChildRoutes
} from './utils';

/**
 * - root: a top-level entry of the expanded menu, with its icon
 * - sub: a child of a root entry
 * - popover: a first-level entry of the collapsed menu popover
 * - subsub: any deeper entry
 */
export type EntryVariant = 'root' | 'sub' | 'popover' | 'subsub';

const rowClassNames: Record<EntryVariant, string> = {
  popover:
    'min-h-8 gap-1 px-2 py-1.5 text-sm hover:bg-white/10 dark:hover:bg-white/6',
  root: 'min-h-11 gap-2 p-2 text-sm font-medium hover:bg-white/12 dark:hover:bg-white/8',
  sub: 'min-h-8 gap-1 px-2 py-1.5 text-sm hover:bg-white/10 dark:hover:bg-white/6',
  subsub:
    'min-h-7 gap-1 px-2 py-1 text-[13px] hover:bg-white/10 dark:hover:bg-white/6'
};

const selectedRowClassName =
  'bg-white/40 hover:bg-white/40 dark:hover:bg-white/40';

const childVariants: Record<EntryVariant, EntryVariant> = {
  popover: 'subsub',
  root: 'sub',
  sub: 'subsub',
  subsub: 'subsub'
};

// A vertical line runs along every submenu. Under a root entry, it is
// centred on the icons.
const childListClassNames: Record<EntryVariant, string> = {
  popover: 'ml-2 border-l border-white/25 pt-0.5 pb-1 pl-4',
  root: 'ml-[19px] border-l border-white/25 pt-0.5 pb-1 pl-5',
  sub: 'ml-2 border-l border-white/25 pt-0.5 pb-1 pl-3',
  subsub: 'ml-2 border-l border-white/25 pt-0.5 pb-1 pl-3'
};

interface CaretProps {
  isOpen: boolean;
  variant: EntryVariant;
}

const Caret = ({ isOpen, variant }: CaretProps): ReactElement => (
  <svg
    aria-hidden="true"
    className={`shrink-0 transition-transform duration-[120ms] ${variant === 'root' ? 'size-4' : 'size-3.5'} ${isOpen ? 'rotate-180' : ''}`}
    fill="none"
    stroke="currentColor"
    strokeLinecap="round"
    strokeLinejoin="round"
    strokeWidth="2"
    viewBox="0 0 24 24"
  >
    <polyline points="6 9 12 15 18 9" />
  </svg>
);

interface LabelProps {
  entry: RouteHierarchy;
  variant: EntryVariant;
}

const Label = ({ entry, variant }: LabelProps): ReactElement => {
  const { Icon, badge, label } = entry;

  return (
    <>
      {variant === 'root' && Icon && <Icon className="shrink-0" />}
      <span className="min-w-0 flex-1">{label}</span>
      {badge && (
        <Chip
          className="h-4 text-[10px] font-bold uppercase"
          color="secondary"
          label={badge}
          size="small"
        />
      )}
    </>
  );
};

const rowClassName = (variant: EntryVariant): string =>
  `rounded-sm leading-5 text-white transition-colors duration-[120ms] ${rowClassNames[variant]}`;

interface Props {
  actions: SubRouteActions;
  entry: RouteHierarchy;
  variant: EntryVariant;
}

/** A page. Only the selected one is highlighted, on its whole row. */
export const SubRouteItem = ({
  actions,
  entry,
  variant
}: Props): ReactElement => {
  const { LinkComponent, onSelect, selectedRoute } = actions;
  const isSelected = containsRoute(entry, selectedRoute);
  const linkProps = LinkComponent
    ? { component: LinkComponent, to: entry.route }
    : {};

  return (
    <ListItemButton
      {...linkProps}
      aria-current={isSelected ? 'page' : undefined}
      className={`${rowClassName(variant)} ${isSelected ? selectedRowClassName : ''}`}
      data-selected={isSelected}
      onClick={(): void => onSelect(entry.route)}
    >
      <Label entry={entry} variant={variant} />
    </ListItemButton>
  );
};

interface SubRouteContentProps extends Props {
  isExpanded: boolean;
  onToggle: () => void;
}

/**
 * A page, or an accordion listing the entry children. Its parent decides
 * whether it is expanded, so that one sibling at most is.
 */
export const SubRouteContent = ({
  actions,
  entry,
  isExpanded,
  onToggle,
  variant
}: SubRouteContentProps): ReactElement => {
  if (!hasChildRoutes(entry)) {
    return <SubRouteItem actions={actions} entry={entry} variant={variant} />;
  }

  return (
    <Accordion
      className="border-0 bg-transparent text-inherit shadow-none before:hidden"
      disableGutters
      expanded={isExpanded}
      onChange={onToggle}
      slotProps={{ transition: { unmountOnExit: true } }}
    >
      <AccordionSummary
        classes={{ content: 'm-0 flex items-center gap-[inherit]' }}
        className={rowClassName(variant)}
      >
        <Label entry={entry} variant={variant} />
        <Caret isOpen={isExpanded} variant={variant} />
      </AccordionSummary>
      <AccordionDetails className={`p-0 ${childListClassNames[variant]}`}>
        <SubRouteList
          actions={actions}
          childVariant={childVariants[variant]}
          entry={entry}
        />
      </AccordionDetails>
    </Accordion>
  );
};

interface SubRouteListProps {
  actions: SubRouteActions;
  /** Variant of the listed entries. */
  childVariant: EntryVariant;
  entry: RouteHierarchy;
}

/**
 * The children of an entry: its sub-routes, then its groups. When there are
 * several groups, each labelled group is under an uppercase header. One child
 * at most is expanded.
 */
export const SubRouteList = ({
  actions,
  childVariant,
  entry
}: SubRouteListProps): ReactElement => {
  const { expandedKey, toggle } = useExpandedEntry(
    getChildRoutes(entry),
    actions.selectedRoute
  );
  // A single group has nothing to be told apart from.
  const showsGroupLabels = (entry.groups?.length ?? 0) > 1;

  const renderEntry = (subRoute: RouteHierarchy): ReactElement => {
    const key = getEntryKey(subRoute);

    return (
      <SubRouteContent
        actions={actions}
        entry={subRoute}
        isExpanded={expandedKey === key}
        key={key}
        onToggle={(): void => toggle(key)}
        variant={childVariant}
      />
    );
  };

  return (
    <div className="flex flex-col gap-0.5">
      {entry.subRoutes?.map(renderEntry)}
      {entry.groups?.map((group, index) => (
        <div key={group.label ?? index}>
          {showsGroupLabels && group.label && (
            <p className="m-0 px-2 pt-2 pb-1 text-xs font-bold uppercase text-white/60">
              {group.label}
            </p>
          )}
          <ul
            aria-label={showsGroupLabels ? group.label : undefined}
            className="m-0 flex list-none flex-col gap-0.5 p-0"
          >
            {group.subRoutes.map((subRoute) => (
              <li key={getEntryKey(subRoute)}>{renderEntry(subRoute)}</li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
};
