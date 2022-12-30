import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconButton from '@mui/material/IconButton';
import FirstPageIcon from '@mui/icons-material/FirstPage';
import KeyboardArrowLeft from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRight from '@mui/icons-material/KeyboardArrowRight';
import LastPageIcon from '@mui/icons-material/LastPage';
import { TablePaginationActionsProps } from '@mui/material/TablePagination/TablePaginationActions';

import {
  labelFirstPage,
  labelLastPage,
  labelNextPage,
  labelPreviousPage
} from '../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  root: {
    color: theme.palette.text.secondary,
    flexShrink: 0
  }
}));

const PaginationActions = ({
  onPageChange,
  page,
  rowsPerPage,
  count
}: TablePaginationActionsProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const changeToFirstPage = (event): void => {
    onPageChange(event, 0);
  };

  const changeToPreviousPage = (event): void => {
    onPageChange(event, page - 1);
  };

  const changeToNextPage = (event): void => {
    onPageChange(event, page + 1);
  };

  const lastPage = Math.ceil(count / rowsPerPage) - 1;

  const isFirstPage = page === 0;
  const isLastPage = page >= lastPage;

  const changeToLastPage = (event): void => {
    onPageChange(event, Math.max(0, lastPage));
  };

  return (
    <div className={classes.root}>
      <IconButton
        aria-label={t(labelFirstPage) || ''}
        disabled={isFirstPage}
        size="large"
        onClick={changeToFirstPage}
      >
        <FirstPageIcon />
      </IconButton>
      <IconButton
        aria-label={t(labelPreviousPage) || ''}
        disabled={isFirstPage}
        size="large"
        onClick={changeToPreviousPage}
      >
        <KeyboardArrowLeft />
      </IconButton>
      <IconButton
        aria-label={t(labelNextPage) || ''}
        disabled={isLastPage}
        size="large"
        onClick={changeToNextPage}
      >
        <KeyboardArrowRight />
      </IconButton>
      <IconButton
        aria-label={t(labelLastPage) || ''}
        disabled={isLastPage}
        size="large"
        onClick={changeToLastPage}
      >
        <LastPageIcon />
      </IconButton>
    </div>
  );
};

export default PaginationActions;
