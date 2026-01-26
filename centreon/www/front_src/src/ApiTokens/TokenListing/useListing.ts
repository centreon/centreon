import { useAtom, useSetAtom } from 'jotai';

import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

interface UseListing {
  changePage: (updatedPage: number) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  disableRowCondition: (row) => boolean;
  page?: number;
  setLimit;
  sortf: string;
  sorto: 'asc' | 'desc';
}

const useListing = (): UseListing => {
  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);

  const changeSort = ({ sortOrder, sortField }): void => {
    setSortf(sortField);
    setSorto(sortOrder);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const disableRowCondition = ({ isRevoked }): boolean => isRevoked;

  return {
    changePage,
    changeSort,
    disableRowCondition,
    page,
    setLimit,
    sortf,
    sorto
  };
};

export default useListing;
