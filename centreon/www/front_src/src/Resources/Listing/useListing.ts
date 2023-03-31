import { SetStateAction } from 'react';

import { useSetAtom } from 'jotai';
import { useAtom } from 'jotai';

import { limitAtom, pageAtom } from './listingAtoms';

export interface ListingState {
  page?: number;
  setLimit: (limit: SetStateAction<number>) => void;
  setPage: (page: SetStateAction<number | undefined>) => void;
}

const useListing = (): ListingState => {
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);

  return {
    page,
    setLimit,
    setPage,
  };
};

export default useListing;
