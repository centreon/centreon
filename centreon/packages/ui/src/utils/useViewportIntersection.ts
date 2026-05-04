import {
  type Dispatch,
  type SetStateAction,
  useEffect,
  useRef,
  useState
} from 'react';

interface ViewportIntersectionState {
  isInViewport: boolean;
  setElement: Dispatch<SetStateAction<HTMLElement | null>>;
}

export const useViewportIntersection = (
  options?: IntersectionObserverInit
): ViewportIntersectionState => {
  const [entry, setEntry] = useState<IntersectionObserverEntry | null>(null);
  const [element, setElement] = useState<HTMLElement | null>(null);

  const observer = useRef<IntersectionObserver | null>(null);

  const sanitizedOptions = {
    ...options,
    root:
      options?.root instanceof HTMLElement
        ? `${options.root.tagName}_${options.root.className}`
        : null
  };

  useEffect(() => {
    if (observer.current) {
      observer.current.disconnect();
    }

    observer.current = new window.IntersectionObserver(([newEntry]) => {
      setEntry(newEntry);
    }, options);

    if (element) {
      observer.current.observe(element);
    }

    return (): void => {
      observer.current?.disconnect();
    };
  }, [element, JSON.stringify(sanitizedOptions)]);

  return {
    isInViewport: entry?.isIntersecting ?? true,
    setElement
  };
};
