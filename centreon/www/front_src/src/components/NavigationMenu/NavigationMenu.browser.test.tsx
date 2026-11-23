import { SvgIcon, type SvgIconProps } from '@mui/material';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { forwardRef, type MouseEvent, type ReactElement } from 'react';

import type { RouteHierarchy } from './models';
import NavigationMenu, { type NavigationMenuProps } from './NavigationMenu';

const Icon = (props: SvgIconProps): ReactElement => (
  <SvgIcon {...props}>
    <circle cx="12" cy="12" r="8" />
  </SvgIcon>
);

const routes: Array<RouteHierarchy> = [
  { Icon, label: 'Home', route: '/home' },
  {
    Icon,
    label: 'Configuration',
    route: '/hosts',
    subRoutes: [
      {
        groups: [{ subRoutes: [{ label: 'Hosts list', route: '/hosts' }] }],
        label: 'Hosts',
        route: '/hosts'
      },
      {
        groups: [
          {
            label: 'Management',
            subRoutes: [
              { label: 'Business Views', route: '/ba/views' },
              {
                label: 'Indicators',
                nonDisplayedRoutes: ['/ba/indicators/new'],
                route: '/ba/indicators'
              }
            ]
          },
          {
            label: 'Help',
            subRoutes: [
              {
                badge: 'Beta',
                label: 'Troubleshooter',
                route: '/ba/troubleshooter'
              }
            ]
          }
        ],
        label: 'Business Activity',
        route: '/ba/views'
      }
    ]
  }
];

const labels = {
  collapse: 'Close panel',
  expand: 'Open panel',
  navigation: 'Main navigation'
};

const renderMenu = (
  props: Partial<NavigationMenuProps> = {}
): Record<
  'onNavigate' | 'onPopoverOpenChange' | 'onToggle',
  ReturnType<typeof rs.fn>
> => {
  const onNavigate = rs.fn();
  const onPopoverOpenChange = rs.fn();
  const onToggle = rs.fn();

  render(
    <NavigationMenu
      isOpen={false}
      labels={labels}
      onNavigate={onNavigate}
      onPopoverOpenChange={onPopoverOpenChange}
      onToggle={onToggle}
      routes={routes}
      {...props}
    />
  );

  return { onNavigate, onPopoverOpenChange, onToggle };
};

const getPopover = (): HTMLElement =>
  screen.getByRole('region', { name: 'Configuration' });

describe('NavigationMenu', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('collapsed', () => {
    it('opens the popover of a parent entry with its groups when it is clicked', async () => {
      const { onPopoverOpenChange } = renderMenu();

      await userEvent.click(
        screen.getByRole('button', { name: 'Configuration' })
      );

      const popover = getPopover();

      expect(
        within(popover).getByRole('button', { name: 'Hosts' })
      ).toBeVisible();
      await userEvent.click(
        within(popover).getByRole('button', { name: 'Business Activity' })
      );
      expect(
        await within(popover).findByRole('list', { name: 'Management' })
      ).toBeVisible();
      expect(within(popover).getByRole('list', { name: 'Help' })).toBeVisible();
      expect(within(popover).getByText('Beta')).toBeVisible();
      expect(onPopoverOpenChange).toHaveBeenLastCalledWith(true);
    });

    it('navigates and closes the popover when a leaf is clicked', async () => {
      const { onNavigate, onPopoverOpenChange } = renderMenu();

      await userEvent.click(
        screen.getByRole('button', { name: 'Configuration' })
      );
      await userEvent.click(
        within(getPopover()).getByRole('button', { name: 'Business Activity' })
      );
      await userEvent.click(
        await screen.findByRole('button', { name: 'Indicators' })
      );

      expect(onNavigate).toHaveBeenCalledWith('/ba/indicators');
      expect(onPopoverOpenChange).toHaveBeenLastCalledWith(false);
      await waitFor(() => {
        expect(
          screen.queryByRole('region', { name: 'Configuration' })
        ).not.toBeInTheDocument();
      });
    });

    it('navigates directly when a top-level leaf is clicked', async () => {
      const { onNavigate } = renderMenu();

      await userEvent.click(screen.getByRole('button', { name: 'Home' }));

      expect(onNavigate).toHaveBeenCalledWith('/home');
      expect(
        screen.queryByRole('region', { name: 'Home' })
      ).not.toBeInTheDocument();
    });

    it('navigates to the route of a parent entry when it is double-clicked', async () => {
      const { onNavigate } = renderMenu();

      await userEvent.dblClick(
        screen.getByRole('button', { name: 'Configuration' })
      );

      expect(onNavigate).toHaveBeenCalledWith('/hosts');
    });

    it('hides the rail scrollbar and gives the popover an arrow-less translucent one', async () => {
      renderMenu();

      expect(getComputedStyle(screen.getByRole('list')).scrollbarWidth).toBe(
        'none'
      );

      await userEvent.click(
        screen.getByRole('button', { name: 'Configuration' })
      );

      const popover = getPopover();
      const popoverStyle = getComputedStyle(popover);

      // Chromium: the standard properties are reset so that the webkit
      // pseudo-elements, without arrow buttons, style the scrollbar.
      expect(popoverStyle.scrollbarWidth).toBe('auto');
      expect(popoverStyle.scrollbarColor).toBe('auto');
      expect(
        getComputedStyle(popover, '::-webkit-scrollbar-button').display
      ).toBe('none');
      expect(
        getComputedStyle(popover, '::-webkit-scrollbar-thumb').backgroundColor
      ).toMatch(/^oklab\(0\.99\d* .* \/ 0\.25\)$/);
    });

    it('closes the popover when the Escape key is pressed', async () => {
      renderMenu();

      await userEvent.click(
        screen.getByRole('button', { name: 'Configuration' })
      );
      expect(getPopover()).toBeVisible();

      await userEvent.keyboard('{Escape}');

      expect(
        screen.queryByRole('region', { name: 'Configuration' })
      ).not.toBeInTheDocument();
    });

    it('highlights the path to a non-displayed route and expands its accordion', async () => {
      renderMenu({ selectedRoute: '/ba/indicators/new' });

      const configuration = screen.getByRole('button', {
        name: 'Configuration'
      });

      expect(configuration).toHaveAttribute('data-selected', 'true');
      expect(screen.getByRole('button', { name: 'Home' })).toHaveAttribute(
        'data-selected',
        'false'
      );

      await userEvent.click(configuration);

      const popover = getPopover();

      expect(
        within(popover).getByRole('button', { name: 'Business Activity' })
      ).toHaveAttribute('aria-expanded', 'true');
      expect(
        within(popover).getByRole('button', { name: 'Hosts' })
      ).toHaveAttribute('aria-expanded', 'false');
      expect(
        within(popover).getByRole('button', { name: 'Indicators' })
      ).toHaveAttribute('aria-current', 'page');
    });

    it('renders the leaves with the given link component', async () => {
      const clickedLinks: Array<string> = [];
      const Link = forwardRef<
        HTMLAnchorElement,
        { onClick?: (event: MouseEvent<HTMLAnchorElement>) => void; to: string }
      >(({ to, onClick, ...props }, ref) => (
        <a
          {...props}
          href={to}
          onClick={(event): void => {
            event.preventDefault();
            clickedLinks.push(to);
            onClick?.(event);
          }}
          ref={ref}
        />
      ));
      const { onNavigate } = renderMenu({ LinkComponent: Link });

      await userEvent.click(
        screen.getByRole('button', { name: 'Configuration' })
      );
      await userEvent.click(
        within(getPopover()).getByRole('button', { name: 'Hosts' })
      );

      const link = await screen.findByText('Hosts list');

      expect(link.closest('a')).toHaveAttribute('href', '/hosts');

      await userEvent.click(link);

      expect(clickedLinks).toEqual(['/hosts']);
      expect(onNavigate).not.toHaveBeenCalled();
    });
  });

  describe('expanded', () => {
    it('displays the entries inline and toggles the menu', async () => {
      const { onToggle } = renderMenu({
        isOpen: true,
        selectedRoute: '/ba/views'
      });

      expect(
        screen.queryByRole('region', { name: 'Configuration' })
      ).not.toBeInTheDocument();
      expect(
        screen.getByRole('button', { name: 'Configuration' })
      ).toHaveAttribute('aria-expanded', 'true');
      expect(
        screen.getByRole('button', { name: 'Business Views' })
      ).toHaveAttribute('aria-current', 'page');

      await userEvent.click(
        screen.getByRole('button', { name: 'Close panel' })
      );

      expect(onToggle).toHaveBeenCalledTimes(1);
    });
  });
});
