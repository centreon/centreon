import { useAtom, useSetAtom } from 'jotai';
import { SetStateAction } from 'react';

import { currentCursorIndexAtom, limitAtom } from './listingAtoms';

export interface ListingState {
  currentCursorIndex: number;
  setCurrentCursorIndex: (index: SetStateAction<number>) => void;
  setLimit: (limit: SetStateAction<number>) => void;
}

const useListing = (): ListingState => {
  const [currentCursorIndex, setCurrentCursorIndex] = useAtom(
    currentCursorIndexAtom
  );
  const setLimit = useSetAtom(limitAtom);

  return {
    currentCursorIndex,
    setCurrentCursorIndex,
    setLimit
  };
};

export default useListing;
