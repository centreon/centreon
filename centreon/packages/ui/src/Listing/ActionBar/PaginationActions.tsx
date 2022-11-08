import React from 'react';

import { useTranslation } from 'react-i18next';

import { makeStyles } from '@material-ui/core';
import IconButton from '@material-ui/core/IconButton';
import FirstPageIcon from '@material-ui/icons/FirstPage';
import KeyboardArrowLeft from '@material-ui/icons/KeyboardArrowLeft';
import KeyboardArrowRight from '@material-ui/icons/KeyboardArrowRight';
import LastPageIcon from '@material-ui/icons/LastPage';
import { TablePaginationActionsProps } from '@material-ui/core/TablePagination/TablePaginationActions';

import {
  labelFirstPage,
  labelLastPage,
  labelNextPage,
  labelPreviousPage,
} from '../translatedLabels';

const useStyles = makeStyles((theme) => ({
  root: {
    color: theme.palette.text.secondary,
    flexShrink: 0,
  },
}));

const PaginationActions = ({
  onPageChange,
  page,
  rowsPerPage,
  count,
}: TablePaginationActionsProps): JSX.Element => {
  const classes = useStyles();
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
        aria-label={t(labelFirstPage)}
        disabled={isFirstPage}
        onClick={changeToFirstPage}
      >
        <FirstPageIcon />
      </IconButton>
      <IconButton
        aria-label={t(labelPreviousPage)}
        disabled={isFirstPage}
        onClick={changeToPreviousPage}
      >
        <KeyboardArrowLeft />
      </IconButton>
      <IconButton
        aria-label={t(labelNextPage)}
        disabled={isLastPage}
        onClick={changeToNextPage}
      >
        <KeyboardArrowRight />
      </IconButton>
      <IconButton
        aria-label={t(labelLastPage)}
        disabled={isLastPage}
        onClick={changeToLastPage}
      >
        <LastPageIcon />
      </IconButton>
    </div>
  );
};

export default PaginationActions;
