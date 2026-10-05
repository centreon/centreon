import { Fade, Popper } from '@mui/material';

import { equals, isEmpty, isNil } from 'ramda';
import { MouseEvent, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Page } from '../models';
import { labelExpandPanel } from '../translatedLabels';
import { MenuStateProps } from './ExpandedMenu';
import { getSectionIcon, NavIcon, navIcons } from './NavIcon';
import { SubRows } from './SubRows';
import {
  iconHover,
  navBg,
  navScrollbar,
  navSelected,
  navTipShadow,
  widthCollapsed,
  widthExpanded
} from './tokens';
import { getPageKey, getPageUrl } from './useMenu';

export interface TipState {
  anchorEl: HTMLElement;
  section: Page;
}

interface CollapsedMenuProps extends MenuStateProps {
  onExpand: () => void;
  onOpenTip: (event: MouseEvent<HTMLElement>, section: Page) => void;
  tip: TipState | null;
}

// Tip sits 4px right of the 44px rail (icon right edge at 36px), and stays
// inside the rail box vertically: it moves up rather than going past the
// rail bottom, its height being capped to the rail height.
const getTipModifiers = (rail: HTMLElement | null) => [
  { name: 'offset', options: { offset: [0, 12] } },
  { enabled: false, name: 'flip' },
  {
    name: 'preventOverflow',
    options: { altAxis: false, boundary: rail ?? 'clippingParents' }
  }
];

const iconClassName = (isActive: boolean): string =>
  `inline-flex size-7 shrink-0 cursor-pointer items-center justify-center rounded text-white transition-colors ${isActive ? navSelected : iconHover}`;

export const CollapsedMenu = ({
  sections,
  tip,
  onExpand,
  onOpenTip,
  isBranchSelected,
  ...subRowsProps
}: CollapsedMenuProps): JSX.Element => {
  const { t } = useTranslation();
  const railRef = useRef<HTMLDivElement | null>(null);

  const isSectionActive = (section: Page): boolean =>
    isNil(tip)
      ? isBranchSelected(section)
      : equals(getPageKey(tip.section), getPageKey(section));

  const railHeight = railRef.current?.getBoundingClientRect().height;

  return (
    <>
      <div
        className={`${navBg} ${widthCollapsed} ${navScrollbar} flex h-full flex-col items-center gap-6 overflow-y-auto overflow-x-hidden rounded-md pt-2 pb-3`}
        data-testid="sidebar"
        ref={railRef}
      >
        <button
          aria-expanded={false}
          aria-label={t(labelExpandPanel)}
          className={iconClassName(false)}
          data-testid={labelExpandPanel}
          onClick={onExpand}
          title={t(labelExpandPanel)}
          type="button"
        >
          <NavIcon src={navIcons.expand} />
        </button>

        {sections.map((section) => {
          const label = t(section.label);
          const icon = <NavIcon src={getSectionIcon(section)} />;

          if (isEmpty(section.children ?? [])) {
            return (
              <Link
                aria-label={label}
                className={iconClassName(isSectionActive(section))}
                data-testid={section.label}
                key={getPageKey(section)}
                onClick={subRowsProps.onNavigate}
                title={label}
                to={getPageUrl(section)}
              >
                {icon}
              </Link>
            );
          }

          return (
            <button
              aria-expanded={equals(tip?.section, section)}
              aria-haspopup="menu"
              aria-label={label}
              className={iconClassName(isSectionActive(section))}
              data-testid={section.label}
              key={getPageKey(section)}
              onClick={(event) => onOpenTip(event, section)}
              title={label}
              type="button"
            >
              {icon}
            </button>
          );
        })}
      </div>

      <Popper
        anchorEl={tip?.anchorEl}
        modifiers={getTipModifiers(railRef.current)}
        open={!isNil(tip)}
        placement="right-start"
        sx={{ zIndex: 1300 }}
        transition
      >
        {({ TransitionProps }) => (
          <Fade {...TransitionProps}>
            <div
              className={`${navBg} ${widthExpanded} ${navTipShadow} ${navScrollbar} overflow-y-auto rounded-md p-2 text-white`}
              style={{ maxHeight: railHeight ?? 'calc(100vh - 16px)' }}
            >
              {tip && (
                <>
                  <div className="p-2 font-medium text-sm leading-5">
                    {t(tip.section.label)}
                  </div>
                  <SubRows isInTip section={tip.section} {...subRowsProps} />
                </>
              )}
            </div>
          </Fade>
        )}
      </Popper>
    </>
  );
};
