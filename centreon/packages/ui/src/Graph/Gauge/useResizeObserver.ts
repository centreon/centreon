import {
  type RefObject,
  useCallback,
  useEffect,
  useRef,
  useState
} from 'react';

interface UseResizeObserverState {
  width: number;
  height: number;
  ref: RefObject<HTMLDivElement | null>;
}

export const useResizeObserver = (): UseResizeObserverState => {
  const [elementResolved, setElementResolved] = useState(false);
  const [size, setSize] = useState([0, 0]);
  const targetRef = useRef<HTMLDivElement | null>(null);
  const observerRef = useRef<ResizeObserver | null>(null);

  const resize = useCallback((entries: Array<ResizeObserverEntry>): void => {
    setSize([entries[0].contentRect.width, entries[0].contentRect.height]);
  }, []);

  const observeElement = useCallback(() => {
    if (!targetRef.current) {
      return;
    }

    observerRef.current = new ResizeObserver(resize);
    observerRef.current?.observe(targetRef.current);
  }, [resize, targetRef.current]);

  const unobserveElement = useCallback((): void => {
    if (!targetRef.current) {
      return;
    }

    observerRef.current?.unobserve(targetRef.current);
    observerRef.current = null;
    setElementResolved(false);
  }, []);

  useEffect(() => {
    if (elementResolved) {
      return;
    }
    setElementResolved(!!targetRef.current?.tagName);
  });

  useEffect(() => {
    if (!elementResolved) {
      return;
    }

    observeElement();

    return () => {
      unobserveElement();
    };
  }, [elementResolved]);

  return {
    width: size[0],
    height: size[1],
    ref: targetRef
  };
};
