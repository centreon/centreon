import FirstPageIcon from '@mui/icons-material/FirstPage';
import KeyboardArrowLeft from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRight from '@mui/icons-material/KeyboardArrowRight';
import LastPageIcon from '@mui/icons-material/LastPage';
import IconButton from '@mui/material/IconButton';

type TablePaginationActionsProps = {
  count: number;
  onPageChange: (
    event: React.MouseEvent<HTMLButtonElement> | null,
    page: number
  ) => void;
  page: number;
  rowsPerPage: number;
};

import { useTranslation } from 'react-i18next';

import {
  labelFirstPage,
  labelLastPage,
  labelNextPage,
  labelPreviousPage
} from '../translatedLabels';

const PaginationActions = ({
  onPageChange,
  page,
  rowsPerPage,
  count
}: TablePaginationActionsProps): JSX.Element => {
  const { t } = useTranslation();

  const changeToFirstPage = (
    event: React.MouseEvent<HTMLButtonElement>
  ): void => {
    onPageChange(event, 0);
  };

  const changeToPreviousPage = (
    event: React.MouseEvent<HTMLButtonElement>
  ): void => {
    onPageChange(event, page - 1);
  };

  const changeToNextPage = (
    event: React.MouseEvent<HTMLButtonElement>
  ): void => {
    onPageChange(event, page + 1);
  };

  const lastPage = Math.ceil(count / rowsPerPage) - 1;

  const isFirstPage = page === 0;
  const isLastPage = page >= lastPage;

  const changeToLastPage = (
    event: React.MouseEvent<HTMLButtonElement>
  ): void => {
    onPageChange(event, Math.max(0, lastPage));
  };

  return (
    <div className="shrink-0 text-text-secondary">
      <IconButton
        aria-label={t(labelFirstPage) || ''}
        disabled={isFirstPage}
        onClick={changeToFirstPage}
        size="large"
      >
        <FirstPageIcon />
      </IconButton>
      <IconButton
        aria-label={t(labelPreviousPage) || ''}
        disabled={isFirstPage}
        onClick={changeToPreviousPage}
        size="large"
      >
        <KeyboardArrowLeft />
      </IconButton>
      <IconButton
        aria-label={t(labelNextPage) || ''}
        disabled={isLastPage}
        onClick={changeToNextPage}
        size="large"
      >
        <KeyboardArrowRight />
      </IconButton>
      <IconButton
        aria-label={t(labelLastPage) || ''}
        disabled={isLastPage}
        onClick={changeToLastPage}
        size="large"
      >
        <LastPageIcon />
      </IconButton>
    </div>
  );
};

export default PaginationActions;
