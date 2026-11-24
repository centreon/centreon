import ExpandMoreRounded from '@mui/icons-material/ExpandMoreRounded';
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Chip,
  ListItemButton
} from '@mui/material';

import type { ReactElement } from 'react';

import type { RouteHierarchy, SubRouteActions } from './models';
import { containsRoute, getChildRoutes, hasChildRoutes } from './utils';

/**
 * - root: a top-level entry of the expanded menu, with its icon
 * - section: a first-level entry of the popover
 * - nested: any deeper entry
 */
export type EntryVariant = 'root' | 'section' | 'nested';

interface Props {
  actions: SubRouteActions;
  entry: RouteHierarchy;
  variant?: EntryVariant;
}

const rowClassNames: Record<EntryVariant, string> = {
  nested: 'px-1 py-0.5 text-xs hover:bg-transparent',
  root: 'px-2 py-2 text-sm font-medium hover:bg-white/20',
  section: 'px-1 py-1 text-sm hover:bg-transparent'
};

// Root entries are highlighted by their icon, the others by a pill
// around their label.
const usesLabelPill = (variant: EntryVariant): boolean => variant !== 'root';

interface LabelProps {
  entry: RouteHierarchy;
  isHighlighted: boolean;
  variant: EntryVariant;
}

const Label = ({ entry, isHighlighted, variant }: LabelProps): ReactElement => {
  const { Icon, badge, label } = entry;
  const pillClassName = isHighlighted
    ? 'bg-white/40'
    : 'group-hover:bg-white/20';

  return (
    <>
      {variant === 'root' && Icon && (
        <span
          className={`flex shrink-0 rounded-sm p-0.5 ${isHighlighted ? 'bg-white/40' : ''}`}
        >
          <Icon fontSize="small" />
        </span>
      )}
      <span
        className={
          usesLabelPill(variant)
            ? `rounded-sm px-1 py-0.5 leading-tight ${pillClassName}`
            : 'flex-1 leading-tight'
        }
      >
        {label}
      </span>
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

export const SubRouteItem = ({
  actions,
  entry,
  variant = 'nested'
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
      className={`group gap-2 rounded-sm ${rowClassNames[variant]}`}
      data-selected={isSelected}
      onClick={(): void => onSelect(entry.route)}
    >
      <Label entry={entry} isHighlighted={isSelected} variant={variant} />
    </ListItemButton>
  );
};

/**
 * A leaf, or an accordion listing the entry children. The accordion starts
 * expanded when it contains the selected route.
 */
export const SubRouteContent = ({
  actions,
  entry,
  variant = 'nested'
}: Props): ReactElement => {
  if (!hasChildRoutes(entry)) {
    return <SubRouteItem actions={actions} entry={entry} variant={variant} />;
  }

  const isSelected = containsRoute(entry, actions.selectedRoute);
  // The guide line only runs along lists made of pages.
  const hasOnlyLeaves = getChildRoutes(entry).every(
    (child) => !hasChildRoutes(child)
  );
  const list = <SubRouteList actions={actions} entry={entry} />;

  return (
    <Accordion
      className="bg-transparent text-inherit shadow-none before:hidden"
      defaultExpanded={isSelected}
      disableGutters
      slotProps={{ transition: { unmountOnExit: true } }}
    >
      <AccordionSummary
        classes={{ content: 'my-0 flex items-center gap-2' }}
        className={`group min-h-0 rounded-sm ${rowClassNames[variant]}`}
        expandIcon={
          <ExpandMoreRounded className="text-white" fontSize="small" />
        }
      >
        <Label
          entry={entry}
          isHighlighted={variant === 'root' && isSelected}
          variant={variant}
        />
      </AccordionSummary>
      <AccordionDetails
        className={`py-1 pr-0 ${variant === 'root' ? 'pl-8' : 'pl-2'}`}
      >
        {hasOnlyLeaves ? (
          <div className="grid grid-cols-[auto_1fr] gap-1">
            <div
              aria-hidden="true"
              className="h-full w-0.5 rounded-full bg-white/40"
              data-testid="guide-line"
            />
            {list}
          </div>
        ) : (
          list
        )}
      </AccordionDetails>
    </Accordion>
  );
};

interface SubRouteListProps extends Omit<Props, 'variant'> {
  /** Variant of the listed entries, nested by default. */
  childVariant?: EntryVariant;
}

/**
 * The children of an entry: its sub-routes, then its groups, each group under
 * an uppercase header when it has a label.
 */
export const SubRouteList = ({
  actions,
  childVariant = 'nested',
  entry
}: SubRouteListProps): ReactElement => (
  <div className="flex flex-col gap-0.5">
    {entry.subRoutes?.map((subRoute) => (
      <SubRouteContent
        actions={actions}
        entry={subRoute}
        key={`${subRoute.label}-${subRoute.route}`}
        variant={childVariant}
      />
    ))}
    {entry.groups?.map((group, index) => (
      <div key={group.label ?? index}>
        {group.label && (
          <p className="m-0 px-2 pt-2 pb-1 text-xs font-bold uppercase text-white/60">
            {group.label}
          </p>
        )}
        <ul
          aria-label={group.label}
          className="m-0 flex list-none flex-col gap-0.5 p-0"
        >
          {group.subRoutes.map((subRoute) => (
            <li key={`${subRoute.label}-${subRoute.route}`}>
              <SubRouteContent
                actions={actions}
                entry={subRoute}
                variant={childVariant}
              />
            </li>
          ))}
        </ul>
      </div>
    ))}
  </div>
);
