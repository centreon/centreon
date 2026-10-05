import { ClickAwayListener } from '@mui/material';

import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { MouseEvent, useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Page } from '../models';
import { isSidebarOpenAtom } from '../navigationAtoms';
import { labelMainNavigation } from '../translatedLabels';
import { CollapsedMenu, TipState } from './CollapsedMenu';
import { ExpandedMenu } from './ExpandedMenu';
import { findActiveTrail, useMenu } from './useMenu';

interface MenuProps {
  navigationData?: Array<Page>;
}

const noSections: Array<Page> = [];

const toggleKey =
  (key: string) =>
  (current: string | null): string | null =>
    equals(current, key) ? null : key;

export const Menu = ({
  navigationData = noSections
}: MenuProps): JSX.Element => {
  const { t } = useTranslation();
  const [isSidebarOpen, setIsSidebarOpen] = useAtom(isSidebarOpenAtom);
  const [openSectionKey, setOpenSectionKey] = useState<string | null>(null);
  const [openSubKey, setOpenSubKey] = useState<string | null>(null);
  const [tip, setTip] = useState<TipState | null>(null);

  const { isBranchSelected, isSelected } = useMenu();

  const openActiveTrail = useCallback((): void => {
    const trail = findActiveTrail(navigationData, isBranchSelected);

    setOpenSectionKey(trail?.sectionKey ?? null);
    setOpenSubKey(trail?.subKey ?? null);
  }, [navigationData, isBranchSelected]);

  // Open the section and level 2 entry holding the current page; only one
  // section and one level 2 entry are open at a time.
  useEffect(() => {
    openActiveTrail();
  }, [openActiveTrail]);

  const closeTip = useCallback((): void => {
    setTip(null);
  }, []);

  const toggleSidebar = (): void => {
    setIsSidebarOpen((prev) => !prev);
    closeTip();
    openActiveTrail();
  };

  const toggleSection = (key: string): void => {
    setOpenSectionKey(toggleKey(key));
  };

  const toggleSub = (key: string): void => {
    setOpenSubKey(toggleKey(key));
  };

  const openTip = (event: MouseEvent<HTMLElement>, section: Page): void => {
    if (equals(tip?.section, section)) {
      closeTip();

      return;
    }

    const trail = findActiveTrail([section], isBranchSelected);

    setOpenSubKey(trail?.subKey ?? null);
    setTip({ anchorEl: event.currentTarget, section });
  };

  // Clicks inside the legacy iframe never reach the top document:
  // the window loses focus instead, so close the tip on blur.
  useEffect(() => {
    const closeOnIframeFocus = (): void => {
      if (equals(document.activeElement?.tagName, 'IFRAME')) {
        closeTip();
      }
    };

    window.addEventListener('blur', closeOnIframeFocus);

    return () => window.removeEventListener('blur', closeOnIframeFocus);
  }, [closeTip]);

  const menuStateProps = {
    isBranchSelected,
    isSelected,
    onNavigate: closeTip,
    onToggleSub: toggleSub,
    openSubKey,
    sections: navigationData
  };

  return (
    <nav aria-label={t(labelMainNavigation)} className="h-full">
      <ClickAwayListener onClickAway={closeTip}>
        <div className="h-full">
          {isSidebarOpen ? (
            <ExpandedMenu
              {...menuStateProps}
              onCollapse={toggleSidebar}
              onToggleSection={toggleSection}
              openSectionKey={openSectionKey}
            />
          ) : (
            <CollapsedMenu
              {...menuStateProps}
              onExpand={toggleSidebar}
              onOpenTip={openTip}
              tip={tip}
            />
          )}
        </div>
      </ClickAwayListener>
    </nav>
  );
};
