import React from 'react';
import PropTypes from 'prop-types';
import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';

function ActionBar(props) {
  const {
    page,
    isLastPage,
    isSubmitting,
    onCancel,
    onPrevious,
    onNext,
    onFinish,
    labelCancel,
    labelPrevious,
    labelNext,
    labelFinish,
  } = props;

  return (
    <Grid container direction="row" justify="space-between" alignItems="center">
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
            disabled={isSubmitting}
            onClick={onFinish}
          >
            {labelFinish}
          </Button>
        ) : (
          <Button type="submit" color="primary" onClick={onNext}>
            {labelNext}
          </Button>
        )}
      </Grid>
    </Grid>
  );
}

ActionBar.propTypes = {
  page: PropTypes.number,
  isLastPage: PropTypes.bool,
  isSubmitting: PropTypes.bool,
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
  page: 0,
  isLastPage: true,
  isSubmitting: false,
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
