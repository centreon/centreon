import { equals, isEmpty } from 'ramda';
import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Page } from '../models';
import { labelClosePanel } from '../translatedLabels';
import { Caret, getSectionIcon, NavIcon, navIcons } from './NavIcon';
import { SubRows } from './SubRows';
import {
  itemHover,
  navBg,
  navScrollbar,
  navSelected,
  widthExpanded
} from './tokens';
import { getPageKey, getPageUrl } from './useMenu';

export interface MenuStateProps {
  isBranchSelected: (page: Page) => boolean;
  isSelected: (page: Page) => boolean;
  onNavigate: () => void;
  onToggleSub: (key: string) => void;
  openSubKey: string | null;
  sections: Array<Page>;
}

interface ExpandedMenuProps extends MenuStateProps {
  onCollapse: () => void;
  onToggleSection: (key: string) => void;
  openSectionKey: string | null;
}

const itemClassName = (isActive: boolean): string =>
  `flex min-h-11 w-full shrink-0 cursor-pointer items-center gap-2 rounded p-2 text-left font-medium text-sm text-white leading-5 no-underline transition-colors ${isActive ? navSelected : itemHover}`;

export const ExpandedMenu = ({
  sections,
  openSectionKey,
  onCollapse,
  onToggleSection,
  isSelected,
  ...subRowsProps
}: ExpandedMenuProps): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div
      className={`${navBg} ${widthExpanded} ${navScrollbar} flex h-full flex-col gap-2 overflow-y-auto overflow-x-hidden rounded-md px-2 pb-3`}
      data-testid="sidebar"
    >
      <button
        aria-expanded
        aria-label={t(labelClosePanel)}
        className={`${itemClassName(false)} rounded-b-none border-white/18 border-b`}
        data-testid={labelClosePanel}
        onClick={onCollapse}
        title={t(labelClosePanel)}
        type="button"
      >
        <NavIcon src={navIcons.collapse} />
      </button>

      {sections.map((section) => {
        const key = getPageKey(section);
        const label = t(section.label);
        const icon = <NavIcon src={getSectionIcon(section)} />;

        if (isEmpty(section.children ?? [])) {
          return (
            <Link
              className={itemClassName(isSelected(section))}
              data-testid={section.label}
              key={key}
              onClick={subRowsProps.onNavigate}
              to={getPageUrl(section)}
            >
              {icon}
              <span className="min-w-0 flex-1">{label}</span>
            </Link>
          );
        }

        const isOpen = equals(openSectionKey, key);

        return (
          <Fragment key={key}>
            <button
              aria-expanded={isOpen}
              className={itemClassName(false)}
              data-testid={section.label}
              onClick={() => onToggleSection(key)}
              type="button"
            >
              {icon}
              <span className="min-w-0 flex-1">{label}</span>
              <Caret className="size-4 opacity-90" isOpen={isOpen} />
            </button>
            {isOpen && (
              <SubRows
                isSelected={isSelected}
                section={section}
                {...subRowsProps}
              />
            )}
          </Fragment>
        );
      })}
    </div>
  );
};
