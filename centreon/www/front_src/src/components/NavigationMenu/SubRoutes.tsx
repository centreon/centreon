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
import { containsRoute, hasChildRoutes } from './utils';

interface Props {
  actions: SubRouteActions;
  entry: RouteHierarchy;
  /** Displays the entry icon before its label (top-level entries). */
  withIcon?: boolean;
}

const Label = ({ entry, withIcon }: Omit<Props, 'actions'>): ReactElement => {
  const { Icon, badge, label } = entry;

  return (
    <>
      {withIcon && Icon && <Icon fontSize="small" />}
      <span className="flex-1 leading-tight">{label}</span>
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
  withIcon
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
      className={`gap-2 rounded-sm px-2 py-1 text-sm hover:bg-white/20 ${isSelected ? 'bg-white/40' : ''}`}
      data-selected={isSelected}
      onClick={(): void => onSelect(entry.route)}
    >
      <Label entry={entry} withIcon={withIcon} />
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
  withIcon
}: Props): ReactElement => {
  if (!hasChildRoutes(entry)) {
    return <SubRouteItem actions={actions} entry={entry} withIcon={withIcon} />;
  }

  const isSelected = containsRoute(entry, actions.selectedRoute);

  return (
    <Accordion
      className="bg-transparent text-inherit shadow-none before:hidden"
      defaultExpanded={isSelected}
      disableGutters
      slotProps={{ transition: { unmountOnExit: true } }}
    >
      <AccordionSummary
        classes={{ content: 'my-0 flex items-center gap-2' }}
        className={`min-h-0 gap-1 rounded-sm px-2 py-1 text-sm hover:bg-white/20 ${isSelected ? 'font-bold' : ''}`}
        expandIcon={
          <ExpandMoreRounded className="text-white" fontSize="small" />
        }
      >
        <Label entry={entry} withIcon={withIcon} />
      </AccordionSummary>
      <AccordionDetails className="py-1 pr-0 pl-3">
        <div className="grid grid-cols-[auto_1fr] gap-1">
          <div className="h-full w-0.5 rounded-full bg-white/40" />
          <SubRouteList actions={actions} entry={entry} />
        </div>
      </AccordionDetails>
    </Accordion>
  );
};

/**
 * The children of an entry: its sub-routes, then its groups, each group under
 * an uppercase header when it has a label.
 */
export const SubRouteList = ({
  actions,
  entry
}: Omit<Props, 'withIcon'>): ReactElement => (
  <div className="flex flex-col gap-0.5">
    {entry.subRoutes?.map((subRoute) => (
      <SubRouteContent
        actions={actions}
        entry={subRoute}
        key={`${subRoute.label}-${subRoute.route}`}
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
              <SubRouteContent actions={actions} entry={subRoute} />
            </li>
          ))}
        </ul>
      </div>
    ))}
  </div>
);
