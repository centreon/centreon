import { ClickAwayListener, Divider } from '@mui/material';

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

/** Width of the menu in pixels, for layouts that must offset their content. */
export const navigationMenuWidth = { closed: 48, open: 216 };

const popoverGap = 8;
const popoverMinHeight = 240;

// The global scrollbar (grey thumb on a light track) is unreadable on the
// menu background: a thin translucent thumb on a transparent track instead,
// with a stable gutter so the content does not jump when it overflows.
// Chromium draws its native scrollbar, arrows included, as soon as
// scrollbar-color or scrollbar-width is set: where ::-webkit-scrollbar is
// supported they are reset so that the arrow-less webkit styles apply.
const scrollbarClassName = [
  '[scrollbar-gutter:stable]',
  '[scrollbar-color:rgb(255_255_255/0.25)_transparent] [scrollbar-width:thin] hover:[scrollbar-color:rgb(255_255_255/0.5)_transparent]',
  'supports-[selector(::-webkit-scrollbar)]:[scrollbar-color:auto] supports-[selector(::-webkit-scrollbar)]:[scrollbar-width:auto] supports-[selector(::-webkit-scrollbar)]:hover:[scrollbar-color:auto]',
  '[&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar]:bg-transparent',
  '[&::-webkit-scrollbar-button]:hidden',
  '[&::-webkit-scrollbar-track]:bg-transparent',
  '[&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-white/25',
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
      left: right + popoverGap,
      top: Math.max(
        popoverGap,
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
        className="flex h-full shrink-0 flex-col overflow-hidden bg-primary-main text-white transition-[width] duration-200 dark:bg-black"
        data-testid="sidebar"
        style={{
          width: isOpen ? navigationMenuWidth.open : navigationMenuWidth.closed
        }}
      >
        {header}
        <button
          aria-label={isOpen ? labels.collapse : labels.expand}
          className="mx-1 my-1 flex cursor-pointer items-center gap-3 rounded-sm border-0 bg-transparent px-2 py-2 text-white hover:bg-white/20"
          onClick={toggleMenu}
          type="button"
        >
          <PanelIcon
            className={`transition-[rotate] ${isOpen ? 'rotate-180' : ''}`}
            fontSize="small"
          />
          {isOpen && (
            <span className="whitespace-nowrap text-sm font-bold">
              {labels.collapse}
            </span>
          )}
        </button>
        <Divider className="mx-2 mb-1 border-white/20" />
        <ul
          className={`m-0 flex flex-1 list-none flex-col gap-0.5 overflow-x-hidden overflow-y-auto px-1 py-0 ${isOpen ? scrollbarClassName : hiddenScrollbarClassName}`}
        >
          {routes.map((entry) => (
            <NavigationMenuItem
              actions={actions}
              entry={entry}
              isMenuOpen={isOpen}
              isPopoverOpen={popover?.entry === entry}
              key={`${entry.label}-${entry.route}`}
              onNavigate={navigate}
              onTogglePopover={togglePopover}
            />
          ))}
        </ul>
        {popover && (
          <section
            aria-label={popover.entry.label}
            className={`fixed z-[1000] flex w-64 flex-col overflow-y-auto rounded-lg bg-primary-main p-2 text-white shadow-lg dark:bg-black ${scrollbarClassName}`}
            data-testid="navigation-menu-popover"
            style={{
              left: popover.left,
              maxHeight: `calc(100vh - ${popover.top + popoverGap}px)`,
              top: popover.top
            }}
          >
            <p className="m-0 px-2 pb-2 font-bold">{popover.entry.label}</p>
            <SubRouteList actions={actions} entry={popover.entry} />
          </section>
        )}
      </nav>
    </ClickAwayListener>
  );
};

export default NavigationMenu;
