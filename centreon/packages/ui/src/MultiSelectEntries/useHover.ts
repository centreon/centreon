import { useRef, useState, useEffect, Ref } from 'react';

const useHover = (): Array<Ref<Node> | boolean> => {
  const [value, setValue] = useState<boolean>(false);

  const ref = useRef<Node>(null);

  const mouseHover = (): void => setValue(true);
  const mouseOut = (): void => setValue(false);

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
  }, []);

  return [ref, value];
};

export default useHover;
