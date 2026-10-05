import { equals, length } from 'ramda';
import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Page } from '../models';
import { Caret } from './NavIcon';
import { navSelected, rowHover } from './tokens';
import {
  getBadge,
  getGroups,
  getPageKey,
  getPageUrl,
  hasGroups
} from './useMenu';

interface SubRowsProps {
  isInTip?: boolean;
  isSelected: (page: Page) => boolean;
  onNavigate: () => void;
  onToggleSub: (key: string) => void;
  openSubKey: string | null;
  section: Page;
}

const rowClassName = (isActive: boolean): string =>
  `flex min-h-8 w-full cursor-pointer items-center justify-between gap-1 rounded px-2 py-1.5 text-left font-normal text-sm text-white leading-5 no-underline ${isActive ? navSelected : rowHover}`;

const leafClassName = (isActive: boolean): string =>
  `flex min-h-7 w-full cursor-pointer items-center gap-1 rounded px-2 py-1 text-left font-normal text-[13px] text-white leading-5 no-underline ${isActive ? navSelected : rowHover}`;

const Badge = ({ page }: { page: Page }): JSX.Element | null => {
  const { t } = useTranslation();
  const badge = getBadge(page);

  if (!badge) {
    return null;
  }

  return (
    <span className="shrink-0 rounded bg-white/20 px-1 font-bold text-[10px] uppercase leading-[14px]">
      {t(badge)}
    </span>
  );
};

// Level 2 rows of a section; level 2 entries with groups expand in place
// to show their level 3 pages (one level 2 open at a time).
export const SubRows = ({
  section,
  isInTip = false,
  isSelected,
  openSubKey,
  onNavigate,
  onToggleSub
}: SubRowsProps): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div
      className={
        isInTip ? '' : 'ml-4 border-white/25 border-l pt-0.5 pb-1 pl-8'
      }
    >
      {(section.children ?? []).map((sub) => {
        const key = getPageKey(sub);

        if (!hasGroups(sub)) {
          return (
            <Link
              className={rowClassName(isSelected(sub))}
              data-testid={sub.label}
              key={key}
              onClick={onNavigate}
              to={getPageUrl(sub)}
            >
              <span className="min-w-0 flex-1">{t(sub.label)}</span>
              <Badge page={sub} />
            </Link>
          );
        }

        const isOpen = equals(openSubKey, key);
        const groups = getGroups(sub);
        const displayGroupTitles = length(groups) > 1;

        return (
          <Fragment key={key}>
            <button
              aria-expanded={isOpen}
              className={rowClassName(false)}
              data-testid={sub.label}
              onClick={() => onToggleSub(key)}
              type="button"
            >
              <span className="min-w-0 flex-1">{t(sub.label)}</span>
              <Caret className="size-3.5 opacity-85" isOpen={isOpen} />
            </button>
            {isOpen && (
              <div className="ml-2 border-white/25 border-l pt-0.5 pb-1 pl-1">
                {groups.map((group) => (
                  <Fragment key={getPageKey(group)}>
                    {displayGroupTitles && (
                      <div className="px-2 pt-2 pb-1 font-bold text-[10px] text-white/60 uppercase leading-[14px] tracking-[0.04em]">
                        {t(group.label)}
                      </div>
                    )}
                    {(group.children ?? []).map((leaf) => (
                      <Link
                        className={leafClassName(isSelected(leaf))}
                        data-testid={leaf.label}
                        key={getPageKey(leaf)}
                        onClick={onNavigate}
                        to={getPageUrl(leaf)}
                      >
                        <span className="min-w-0 flex-1">{t(leaf.label)}</span>
                        <Badge page={leaf} />
                      </Link>
                    ))}
                  </Fragment>
                ))}
              </div>
            )}
          </Fragment>
        );
      })}
    </div>
  );
};
