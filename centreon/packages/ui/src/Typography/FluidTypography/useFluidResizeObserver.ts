import {
  MutableRefObject,
  useCallback,
  useEffect,
  useRef,
  useState
} from 'react';

interface Size {
  height: number;
  width: number;
}

interface UseFluidResizeObserver {
  isParent?: boolean;
  ref: MutableRefObject<HTMLElement | undefined>;
}

const useFluidResizeObserver = ({
  ref,
  isParent
}: UseFluidResizeObserver): Size => {
  const [size, setSize] = useState<Size>({ height: 0, width: 0 });

  const observer = useRef<ResizeObserver | null>(null);

  const resizeObserver = useCallback(
    (element) => {
      if (observer.current) {
        observer.current.disconnect();
      }

      observer.current = new ResizeObserver(
        ([entry]: Array<ResizeObserverEntry>) => {
          setSize({
            height: entry.target?.getBoundingClientRect().height || 0,
            width: entry.target?.getBoundingClientRect().width || 0
          });
        }
      );

      if (element && observer.current) {
        observer.current.observe(element);
      }
    },
    [ref.current]
  );

  useEffect(() => {
    resizeObserver(isParent ? ref.current?.parentElement : ref.current);
  }, [ref.current]);

  return size;
};

export default useFluidResizeObserver;
