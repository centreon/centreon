/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';

import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles(() => ({
  icon: {
    color: '#707070',
    cursor: 'initial',
    fontSize: 14,
    verticalAlign: 'middle',
  },
  iconAttach: {
    backgroundColor: '#fff',
    cursor: 'initial',
    display: 'inline-block',
    height: 49,
    lineHeight: '46px',
    marginLeft: -20,
    marginRight: 15,
    textAlign: 'center',
    verticalAlign: 'middle',
    width: 80,
  },
  iconAttachImage: {
    maxHeight: '100%',
    maxWidth: '100%',
    verticalAlign: 'middle',
  },
  iconAttachLabel: {
    color: '#707070',
    display: 'inline-block',
    fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
    fontSize: 12,
    marginLeft: 3,
    verticalAlign: 'middle',
  },
  root: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'center',
  },
}));

const IconAttach = ({
  onClick,
  defaultImage,
  uploadedImage,
  imgSource,
  title,
  labelNoIcon = 'NO ICON',
}) => {
  const classes = useStyles();

  return (
    <span className={classes.iconAttach} onClick={onClick}>
      {defaultImage && (
        <span className={classes.iconAttachLabel}>{labelNoIcon}</span>
      )}
      {uploadedImage && (
        <img
          alt={title}
          className={classes.iconAttachImage}
          src={imgSource}
          title={title}
        />
      )}
    </span>
  );
};

export default IconAttach;
