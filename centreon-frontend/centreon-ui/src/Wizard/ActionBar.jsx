import React from 'react';

import PropTypes from 'prop-types';

import { makeStyles } from '@material-ui/core/styles';
import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';

const useStyles = makeStyles((theme) => ({
  container: {
    position: 'sticky',
    bottom: 0,
    padding: '0px 10px',
    borderTop: `1px solid ${theme.palette.grey[300]}`,
  },
}));

const ActionBar = ({
  disabledNext,
  page,
  isLastPage,
  onCancel,
  onPrevious,
  onNext,
  onFinish,
  labelCancel,
  labelPrevious,
  labelNext,
  labelFinish,
}) => {
  const classes = useStyles();

  return (
    <Grid
      container
      direction="row"
      justify="space-between"
      alignItems="center"
      className={classes.container}
    >
      <Grid item>
        {onCancel && (
          <Button
            type="button"
            color="primary"
            onClick={(event) => onCancel(event, 'cancel')}
          >
            {labelCancel}
          </Button>
        )}
      </Grid>

      <Grid item>
        {page > 0 && (
          <Button type="button" color="primary" onClick={onPrevious}>
            {labelPrevious}
          </Button>
        )}

        {isLastPage ? (
          <Button
            type="submit"
            color="primary"
            disabled={disabledNext}
            onClick={onFinish}
          >
            {labelFinish}
          </Button>
        ) : (
          <Button
            type="submit"
            color="primary"
            onClick={onNext}
            disabled={disabledNext}
          >
            {labelNext}
          </Button>
        )}
      </Grid>
    </Grid>
  );
};

ActionBar.propTypes = {
  disabledNext: PropTypes.bool,
  page: PropTypes.number,
  isLastPage: PropTypes.bool,
  onCancel: PropTypes.func,
  onPrevious: PropTypes.func,
  onNext: PropTypes.func,
  onFinish: PropTypes.func,
  labelCancel: PropTypes.string,
  labelPrevious: PropTypes.string,
  labelNext: PropTypes.string,
  labelFinish: PropTypes.string,
};

ActionBar.defaultProps = {
  disabledNext: false,
  page: 0,
  isLastPage: true,
  onCancel: null,
  onPrevious: null,
  onNext: null,
  onFinish: null,
  labelCancel: 'Cancel',
  labelPrevious: 'Previous',
  labelNext: 'Next',
  labelFinish: 'Finish',
};

export default ActionBar;
