import {
  type RefObject,
  useCallback,
  useEffect,
  useRef,
  useState
} from 'react';

import {
  getSectionAnchors,
  navigateToSection,
  sectionAnchorAttribute
} from './navigateToSection';

// Distance from the top of the scroll area under which a section is the one in
// view: the pinned tab bar plus the scroll margin of the group titles.
const sectionOffset = 64;
// `scrollend` is missing from older browsers, and is not fired when the
// clicked section is already in place.
const scrollEndFallbackDelay = 1000;

const getScrollParent = (element: HTMLElement | null): HTMLElement | null => {
  let parent = element?.parentElement ?? null;

  while (parent && parent !== document.body) {
    if (/(auto|scroll)/.test(getComputedStyle(parent).overflowY)) {
      return parent;
    }
    parent = parent.parentElement;
  }

  return null;
};

interface ComputeActiveSectionProps {
  container: HTMLElement;
  scrollParent: HTMLElement | null;
  sectionNames: Array<string>;
}

const computeActiveSection = ({
  container,
  scrollParent,
  sectionNames
}: ComputeActiveSectionProps): string => {
  const scrollingElement = scrollParent ?? document.documentElement;

  // A tab can have no section in the form: only the sections displayed count.
  const sections = getSectionAnchors(container)
    .filter((anchor) =>
      sectionNames.includes(anchor.getAttribute(sectionAnchorAttribute) || '')
    )
    .map((anchor) => ({
      name: anchor.getAttribute(sectionAnchorAttribute) as string,
      top: anchor.getBoundingClientRect().top
    }));

  if (!sections.length) {
    return sectionNames[0];
  }

  const isScrolledToBottom =
    scrollingElement.scrollTop > 0 &&
    scrollingElement.scrollTop + scrollingElement.clientHeight >=
      scrollingElement.scrollHeight - 1;

  // The last sections can be too short to ever reach the top.
  if (isScrolledToBottom) {
    return (
      [...sectionNames]
        .reverse()
        .find((name) => sections.some((section) => section.name === name)) ||
      sectionNames[0]
    );
  }

  const line =
    (scrollParent ? scrollParent.getBoundingClientRect().top : 0) +
    sectionOffset;

  const passedSections = sections.filter(({ top }) => top <= line);

  if (!passedSections.length) {
    return sectionNames.find((name) =>
      sections.some((section) => section.name === name)
    ) as string;
  }

  return passedSections.reduce((closest, section) =>
    section.top > closest.top ? section : closest
  ).name;
};

interface UseActiveSectionProps {
  containerRef: RefObject<HTMLElement | null>;
  isEnabled: boolean;
  sectionNames: Array<string>;
}

interface UseActiveSection {
  activeSection: string;
  goToSection: (sectionName: string) => void;
}

export const useActiveSection = ({
  containerRef,
  isEnabled,
  sectionNames
}: UseActiveSectionProps): UseActiveSection => {
  const [activeSection, setActiveSection] = useState(sectionNames[0]);

  const isScrollingToSection = useRef(false);
  const scrollTargetRef = useRef<HTMLElement | Window | null>(null);
  const fallbackTimeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(
    undefined
  );
  const stopWaitingRef = useRef<(() => void) | undefined>(undefined);
  const refreshActiveSectionRef = useRef<(() => void) | undefined>(undefined);

  const sectionNamesKey = sectionNames.join('\n');

  const stopWaitingForScrollEnd = useCallback((): void => {
    stopWaitingRef.current?.();
    stopWaitingRef.current = undefined;
    clearTimeout(fallbackTimeoutRef.current);
    isScrollingToSection.current = false;
  }, []);

  // The scroll events fired while waiting are ignored, and none fires when the
  // clicked section is already in place: the position is read again once done.
  const endScrollWait = useCallback((): void => {
    stopWaitingForScrollEnd();
    refreshActiveSectionRef.current?.();
  }, [stopWaitingForScrollEnd]);

  const goToSection = useCallback(
    (sectionName: string): void => {
      stopWaitingForScrollEnd();

      // The clicked tab is the active one until the smooth scroll ends, so the
      // highlight does not run through the sections in between.
      setActiveSection(sectionName);
      isScrollingToSection.current = true;

      const target = scrollTargetRef.current;
      target?.addEventListener('scrollend', endScrollWait, { once: true });
      stopWaitingRef.current = () =>
        target?.removeEventListener('scrollend', endScrollWait);
      fallbackTimeoutRef.current = setTimeout(
        endScrollWait,
        scrollEndFallbackDelay
      );

      navigateToSection({ container: containerRef.current, sectionName });
    },
    [containerRef, endScrollWait, stopWaitingForScrollEnd]
  );

  useEffect(() => {
    const container = containerRef.current;

    if (!isEnabled || !container) {
      return undefined;
    }

    const scrollParent = getScrollParent(container);
    const scrollTarget = scrollParent ?? window;
    scrollTargetRef.current = scrollTarget;

    let animationFrame = 0;

    const refresh = (): void => {
      setActiveSection(
        computeActiveSection({
          container,
          scrollParent,
          sectionNames: sectionNamesKey.split('\n')
        })
      );
    };

    const update = (): void => {
      if (isScrollingToSection.current) {
        return;
      }

      refresh();
    };

    const onScroll = (): void => {
      cancelAnimationFrame(animationFrame);
      animationFrame = requestAnimationFrame(update);
    };

    refreshActiveSectionRef.current = refresh;
    scrollTarget.addEventListener('scroll', onScroll, { passive: true });
    update();

    return () => {
      scrollTarget.removeEventListener('scroll', onScroll);
      cancelAnimationFrame(animationFrame);
      refreshActiveSectionRef.current = undefined;
      stopWaitingForScrollEnd();
    };
  }, [containerRef, isEnabled, sectionNamesKey, stopWaitingForScrollEnd]);

  return { activeSection, goToSection };
};
