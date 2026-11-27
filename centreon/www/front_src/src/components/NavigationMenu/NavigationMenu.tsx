import { ClickAwayListener } from '@mui/material';

import { isNil } from 'ramda';
import {
  type ElementType,
  type ReactElement,
  type ReactNode,
  useCallback,
  useEffect,
  useState
} from 'react';

import type {
  NavigationMenuLabels,
  RouteHierarchy,
  SubRouteActions
} from './models';
import NavigationMenuItem from './NavigationMenuItem';
import PanelIcon from './PanelIcon';
import { SubRouteList } from './SubRoutes';
import { useExpandedEntry } from './useExpandedEntry';
import { getEntryKey } from './utils';

const panelWidth = { closed: 44, open: 212 };
// Matches the px-2 of the menu.
const menuPadding = 8;

/** Width of the menu in pixels, for layouts that must offset their content. */
export const navigationMenuWidth = {
  closed: panelWidth.closed + 2 * menuPadding,
  open: panelWidth.open + 2 * menuPadding
};

// The popover opens 4px from the menu and stays 8px from the viewport edges.
const popoverOffset = 4;
const viewportMargin = 8;
const popoverMinHeight = 240;

// The global scrollbar (grey thumb on a light track) is unreadable on the
// menu background: a thin translucent thumb on a transparent track instead,
// with a stable gutter so the content does not jump when it overflows.
// Chromium draws its native scrollbar, arrows included, as soon as
// scrollbar-color or scrollbar-width is set: where ::-webkit-scrollbar is
// supported they are reset so that the arrow-less webkit styles apply.
const scrollbarClassName = [
  '[scrollbar-gutter:stable]',
  '[scrollbar-color:rgb(255_255_255/0.22)_transparent] [scrollbar-width:thin] hover:[scrollbar-color:rgb(255_255_255/0.5)_transparent]',
  'supports-[selector(::-webkit-scrollbar)]:[scrollbar-color:auto] supports-[selector(::-webkit-scrollbar)]:[scrollbar-width:auto] supports-[selector(::-webkit-scrollbar)]:hover:[scrollbar-color:auto]',
  '[&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar]:bg-transparent',
  '[&::-webkit-scrollbar-button]:hidden',
  '[&::-webkit-scrollbar-track]:bg-transparent',
  '[&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-white/22',
  'hover:[&::-webkit-scrollbar-thumb]:bg-white/50'
].join(' ');

// Even a thin scrollbar would push the icons of the collapsed rail off
// centre: it scrolls without a visible scrollbar.
const hiddenScrollbarClassName = '[scrollbar-width:none]';

export interface NavigationMenuProps {
  /** Displayed above the menu, for instance a logo. */
  header?: ReactNode;
  isOpen: boolean;
  labels: NavigationMenuLabels;
  /**
   * Router link rendered by leaves (receives `to`). Without it, leaves call
   * `onNavigate` on click.
   */
  LinkComponent?: ElementType;
  onNavigate: (route: string) => void;
  onPopoverOpenChange?: (isPopoverOpen: boolean) => void;
  onToggle: () => void;
  routes: Array<RouteHierarchy>;
  /** The displayed route, as found in `routes`. Highlights its ancestors. */
  selectedRoute?: string;
}

interface Popover {
  entry: RouteHierarchy;
  left: number;
  top: number;
}

interface ToggleButtonProps {
  isOpen: boolean;
  labels: NavigationMenuLabels;
  onClick: () => void;
}

/**
 * Expanded menu: a "Close panel" row, underlined. Collapsed menu: a square
 * icon button.
 */
const ToggleButton = ({
  isOpen,
  labels,
  onClick
}: ToggleButtonProps): ReactElement =>
  isOpen ? (
    <button
      aria-label={labels.collapse}
      className="flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-t-sm border-0 border-b border-white/18 bg-transparent p-2 text-white transition-colors duration-[120ms] hover:bg-white/12 dark:hover:bg-white/8"
      onClick={onClick}
      type="button"
    >
      <PanelIcon className="-scale-x-100" />
      <span className="whitespace-nowrap text-sm leading-5 font-medium">
        {labels.collapse}
      </span>
    </button>
  ) : (
    <button
      aria-label={labels.expand}
      className="group flex size-11 shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-white"
      onClick={onClick}
      type="button"
    >
      <span className="flex size-7 items-center justify-center rounded-sm transition-colors duration-[120ms] group-hover:bg-white/16 dark:group-hover:bg-white/8">
        <PanelIcon />
      </span>
    </button>
  );

const NavigationMenu = ({
  header,
  isOpen,
  labels,
  LinkComponent,
  onNavigate,
  onPopoverOpenChange,
  onToggle,
  routes,
  selectedRoute
}: NavigationMenuProps): ReactElement => {
  const [popover, setPopover] = useState<Popover | null>(null);
  const { expandedKey, toggle: toggleExpanded } = useExpandedEntry(
    routes,
    selectedRoute
  );

  const changePopover = useCallback(
    (nextPopover: Popover | null): void => {
      setPopover(nextPopover);
      onPopoverOpenChange?.(!isNil(nextPopover));
    },
    [onPopoverOpenChange]
  );

  const closePopover = useCallback(
    (): void => changePopover(null),
    [changePopover]
  );

  const togglePopover = (entry: RouteHierarchy, element: HTMLElement): void => {
    if (popover?.entry === entry) {
      closePopover();

      return;
    }

    const { right, top } = element.getBoundingClientRect();

    changePopover({
      entry,
      left: right + popoverOffset,
      top: Math.max(
        viewportMargin,
        Math.min(top, window.innerHeight - popoverMinHeight)
      )
    });
  };

  const navigate = (route: string): void => {
    onNavigate(route);
    closePopover();
  };

  const toggleMenu = (): void => {
    closePopover();
    onToggle();
  };

  const actions: SubRouteActions = {
    LinkComponent,
    onSelect: (route): void => {
      if (isNil(LinkComponent)) {
        onNavigate(route);
      }
      closePopover();
    },
    selectedRoute
  };

  useEffect(() => {
    if (isNil(popover)) {
      return undefined;
    }

    const closeOnEscape = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        closePopover();
      }
    };

    // A click inside an iframe (legacy pages) never reaches the document:
    // the window loses the focus instead.
    window.addEventListener('blur', closePopover);
    window.addEventListener('resize', closePopover);
    window.addEventListener('keydown', closeOnEscape);

    return (): void => {
      window.removeEventListener('blur', closePopover);
      window.removeEventListener('resize', closePopover);
      window.removeEventListener('keydown', closeOnEscape);
    };
  }, [popover, closePopover]);

  return (
    <ClickAwayListener onClickAway={closePopover}>
      <nav
        aria-label={labels.navigation}
        className="flex h-full shrink-0 flex-col overflow-hidden bg-background-paper px-2 pb-2 transition-[width] duration-200"
        style={{
          width: isOpen ? navigationMenuWidth.open : navigationMenuWidth.closed
        }}
      >
        {header}
        <div
          className={`flex min-h-0 flex-1 flex-col gap-2 overflow-hidden rounded-md bg-background-navigation text-white ${isOpen ? 'pr-2 pb-3 pl-0.5' : 'items-center pb-3'}`}
          data-testid="sidebar"
        >
          <ToggleButton isOpen={isOpen} labels={labels} onClick={toggleMenu} />
          <ul
            className={`m-0 flex flex-1 list-none flex-col gap-2 overflow-x-hidden overflow-y-auto p-0 ${isOpen ? scrollbarClassName : `items-center ${hiddenScrollbarClassName}`}`}
          >
            {routes.map((entry) => {
              const key = getEntryKey(entry);

              return (
                <NavigationMenuItem
                  actions={actions}
                  entry={entry}
                  isExpanded={expandedKey === key}
                  isMenuOpen={isOpen}
                  isPopoverOpen={popover?.entry === entry}
                  key={key}
                  onNavigate={navigate}
                  onToggleExpanded={(): void => toggleExpanded(key)}
                  onTogglePopover={togglePopover}
                />
              );
            })}
          </ul>
        </div>
        {popover && (
          <section
            aria-label={popover.entry.label}
            className={`fixed z-[1000] flex w-53 flex-col overflow-y-auto rounded-md bg-background-navigation p-2 text-white shadow-[0_8px_24px_rgba(0,0,0,0.35)] ${scrollbarClassName}`}
            data-testid="navigation-menu-popover"
            style={{
              left: popover.left,
              maxHeight: `calc(100vh - ${popover.top + viewportMargin}px)`,
              top: popover.top
            }}
          >
            <p className="m-0 p-2 text-sm leading-5 font-medium">
              {popover.entry.label}
            </p>
            <SubRouteList
              actions={actions}
              childVariant="popover"
              entry={popover.entry}
            />
          </section>
        )}
      </nav>
    </ClickAwayListener>
  );
};

export default NavigationMenu;
