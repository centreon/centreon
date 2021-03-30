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
    flexShrink: 0,
    color: theme.palette.text.secondary,
  },
}));

const PaginationActions = ({
  onChangePage,
  page,
  rowsPerPage,
  count,
}: TablePaginationActionsProps): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  const changeToFirstPage = (event) => {
    onChangePage(event, 0);
  };

  const changeToPreviousPage = (event) => {
    onChangePage(event, page - 1);
  };

  const changeToNextPage = (event) => {
    onChangePage(event, page + 1);
  };

  const lastPage = Math.ceil(count / rowsPerPage) - 1;

  const isFirstPage = page === 0;
  const isLastPage = page >= lastPage;

  const changeToLastPage = (event) => {
    onChangePage(event, Math.max(0, lastPage));
  };

  return (
    <div className={classes.root}>
      <IconButton
        onClick={changeToFirstPage}
        disabled={isFirstPage}
        aria-label={t(labelFirstPage)}
      >
        <FirstPageIcon />
      </IconButton>
      <IconButton
        onClick={changeToPreviousPage}
        disabled={isFirstPage}
        aria-label={t(labelPreviousPage)}
      >
        <KeyboardArrowLeft />
      </IconButton>
      <IconButton
        onClick={changeToNextPage}
        disabled={isLastPage}
        aria-label={t(labelNextPage)}
      >
        <KeyboardArrowRight />
      </IconButton>
      <IconButton
        onClick={changeToLastPage}
        disabled={isLastPage}
        aria-label={t(labelLastPage)}
      >
        <LastPageIcon />
      </IconButton>
    </div>
  );
};

export default PaginationActions;
