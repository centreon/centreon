import { type Ref, useCallback, useEffect, useRef, useState } from 'react';

const useHover = (): Array<Ref<Node> | boolean> => {
  const [value, setValue] = useState<boolean>(false);

  const ref = useRef<Node>(null);

  const mouseHover = useCallback((): void => setValue(true), []);
  const mouseOut = useCallback((): void => setValue(false), []);

  useEffect((): (() => void) | undefined => {
    const node = ref.current;

    if (!node) {
      return undefined;
    }

    node.addEventListener('mouseover', mouseHover);
    node.addEventListener('mouseout', mouseOut);

    return (): void => {
      node.removeEventListener('mouseover', mouseHover);
      node.removeEventListener('mouseout', mouseOut);
    };
  }, [mouseHover, mouseOut]);

  return [ref, value];
};

export default useHover;
