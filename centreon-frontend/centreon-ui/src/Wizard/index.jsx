import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { Formik } from 'formik';
import { makeStyles } from '@material-ui/core/styles';
import Dialog from '@material-ui/core/Dialog';
import DialogContent from '@material-ui/core/DialogContent';
import Box from '@material-ui/core/Box';
import Stepper from './Stepper';
import ActionBar from './ActionBar';
import Confirm from '../Dialog/Confirm';

function isReactElement(element) {
  if (
    element &&
    element.type &&
    ['object', 'function', 'symbol'].includes(typeof element.type)
  ) {
    return true;
  }

  return false;
}

function cloneElement(element, props) {
  const forwardedProps = isReactElement(element) ? props : {};

  return React.cloneElement(element, { ...forwardedProps });
}

const useWizardStyles = makeStyles((theme) => ({
  fullHeight: {
    height: '100%',
  },
  dialogContent: {
    display: 'flex',
    backgroundColor: theme.palette.grey[100],
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    flex: 1,
  },
}));

function Wizard(props) {
  const {
    open,
    onClose,
    initialValues,
    onSubmit,
    width,
    fullHeight,
    actionBarProps,
    exitConfirmProps,
    children,
  } = props;
  const classes = useWizardStyles(props);
  const [page, setPage] = useState(0);
  const [values, setValues] = useState(initialValues);
  const [openConfirm, setOpenConfirm] = useState(false);

  const handleClose = (event, reason) => {
    // close wizard without confirmation if it's the first page
    if (page === 0) {
      onClose('cancel');
    } else {
      setOpenConfirm(true);
      onClose(reason);
    }
  };

  const handleCloseConfirm = (confirm) => {
    setOpenConfirm(false);

    if (confirm === true) {
      onClose('cancel');
    }
  };

  const handleNext = (submittedValues) => {
    setPage(Math.min(page + 1, children.length - 1));
    setValues(submittedValues);
  };

  const handlePrevious = () => {
    setPage(Math.max(page - 1, 0));
  };

  const validate = (currentValues) => {
    const activePage = React.Children.toArray(children)[page];

    return activePage.props.validate
      ? activePage.props.validate(currentValues)
      : {};
  };

  const validationSchema = () => {
    const activePage = React.Children.toArray(children)[page];

    return activePage.props.validationSchema
      ? activePage.props.validationSchema
      : null;
  };

  const handleSubmit = (submittedValues, bag) => {
    const isLastPage = page === React.Children.count(children) - 1;

    if (isLastPage && onSubmit) {
      return onSubmit(submittedValues, bag);
    }

    bag.setTouched({});
    bag.setSubmitting(false);
    handleNext(submittedValues);

    return null;
  };

  const activePage = React.Children.toArray(children)[page];
  const isLastPage = page === React.Children.count(children) - 1;

  return (
    <>
      <Dialog
        classes={{ paper: fullHeight ? classes.fullHeight : null }}
        maxWidth={width}
        fullWidth
        open={open}
        onClose={handleClose}
      >
        <Stepper activeStep={page}>{children}</Stepper>
        <DialogContent className={classes.dialogContent}>
          <Formik
            initialValues={values}
            enableReinitialize={false}
            validate={validate}
            validationSchema={validationSchema()}
            onSubmit={handleSubmit}
          >
            {(bag) => (
              <form className={classes.form} onSubmit={bag.handleSubmit}>
                {cloneElement(activePage, {
                  errors: bag.errors,
                  handleBlur: bag.handleBlur,
                  handleChange: bag.handleChange,
                  handleSubmit: bag.handleSubmit,
                  onPrevious: handlePrevious,
                  onNext: handleNext,
                  setFieldTouched: bag.setFieldTouched,
                  setFieldValue: bag.setFieldValue,
                  submitForm: bag.submitForm,
                  touched: bag.touched,
                  values: bag.values,
                })}
                {!activePage.props.noActionBar && (
                  <ActionBar
                    disabledNext={!bag.isValid || bag.isSubmitting}
                    page={page}
                    isLastPage={isLastPage}
                    onPrevious={handlePrevious}
                    actionBarProps={actionBarProps}
                  />
                )}
              </form>
            )}
          </Formik>
        </DialogContent>
      </Dialog>
      <Confirm
        open={openConfirm}
        onCancel={() => handleCloseConfirm(false)}
        onConfirm={() => handleCloseConfirm(true)}
        {...exitConfirmProps}
      />
    </>
  );
}

Wizard.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  initialValues: PropTypes.objectOf(PropTypes.any),
  onSubmit: PropTypes.func,
  width: PropTypes.string,
  fullHeight: PropTypes.bool,
  actionBarProps: PropTypes.objectOf(PropTypes.any),
  exitConfirmProps: PropTypes.objectOf(PropTypes.any),
  children: PropTypes.node.isRequired,
};

Wizard.defaultProps = {
  onClose: null,
  initialValues: {},
  onSubmit: null,
  fullHeight: false,
  width: 'md',
  actionBarProps: null,
  exitConfirmProps: null,
};

export const Page = ({ children, ...props }) => (
  <Box flex={1}>
    {React.Children.toArray(children).map((child) => {
      return cloneElement(child, props);
    })}
  </Box>
);

Page.propTypes = {
  label: PropTypes.string,
  children: PropTypes.node,
};

Page.defaultProps = {
  label: null,
  children: null,
};

export default Wizard;
