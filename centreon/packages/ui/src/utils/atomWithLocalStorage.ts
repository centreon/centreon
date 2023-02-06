import { atom } from 'jotai';
import type { WritableAtom } from 'jotai';

export const atomWithLocalStorage = <T>(
  key,
  initialValue: T
): WritableAtom<T, [update: unknown], void> => {
  const getInitialValue = (): T => {
    const item = localStorage.getItem(key);
    if (item !== null) {
      return JSON.parse(item);
    }

    return initialValue;
  };
  const baseAtom = atom(getInitialValue());
  const derivedAtom = atom(
    (get) => get(baseAtom),
    (get, set, update) => {
      const nextValue =
        typeof update === 'function' ? update(get(baseAtom)) : update;
      set(baseAtom, nextValue);
      localStorage.setItem(key, JSON.stringify(nextValue));
    }
  );

  return derivedAtom;
};
