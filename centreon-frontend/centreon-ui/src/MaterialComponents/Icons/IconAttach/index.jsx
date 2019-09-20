/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Attach from '@material-ui/icons/AttachFile';

const useStyles = makeStyles(() => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#707070',
    cursor: 'initial',
    fontSize: 14,
    verticalAlign: 'middle',
  },
  iconAttach: {
    width: 80,
    height: 49,
    textAlign: 'center',
    lineHeight: '46px',
    backgroundColor: '#fff',
    display: 'inline-block',
    marginRight: 15,
    verticalAlign: 'middle',
    cursor: 'initial',
    marginLeft: -20,
  },
  iconAttachLabel: {
    fontSize: 12,
    color: '#707070',
    display: 'inline-block',
    verticalAlign: 'middle',
    fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
    marginLeft: 3,
  },
  iconAttachImage: {
    maxWidth: '100%',
    maxHeight: '100%',
    verticalAlign: 'middle',
  },
}));

function IconAttach({
  customStyle,
  onClick,
  defaultImage,
  uploadedImage,
  imgSource,
  title,
}) {
  const classes = useStyles();

  return (
    <span onClick={onClick} className={classes.iconAttach}>
      {defaultImage && (
        <React.Fragment>
          <span className={classes.iconAttachLabel}>NO ICON</span>
        </React.Fragment>
      )}
      {uploadedImage && (
        <img
          src={imgSource}
          className={classes.iconAttachImage}
          alt={title}
          title={title}
        />
      )}
    </span>
  );
}

export default IconAttach;