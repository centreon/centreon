import { type PrimitiveAtom, useStore } from 'jotai';
import { useRef } from 'react';

// Numbers the requests so that only the latest one updates the dialog: the
// answer, or the failure, of a request superseded by a newer one must neither
// show under the newer title nor close the newer dialog. The counter is a ref,
// or an atom when the dialog state lives in the store to survive a remount.
export const useLatestRequest = (
  counterAtom?: PrimitiveAtom<number>
): (() => () => boolean) => {
  const store = useStore();
  const counterRef = useRef(0);

  const read = (): number =>
    counterAtom ? store.get(counterAtom) : counterRef.current;

  return () => {
    const id = read() + 1;

    if (counterAtom) {
      store.set(counterAtom, id);
    } else {
      counterRef.current = id;
    }

    return (): boolean => read() === id;
  };
};
