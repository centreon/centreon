/* eslint-disable no-alert */

import React from 'react';

import ButtonAction from '.';

export default { title: 'Button/Action' };

export const deleteAction = () => (
  <ButtonAction
    iconColor="gray"
    buttonActionType="delete"
    buttonIconType="delete"
    onClick={() => {
      alert("I've been clicked");
    }}
  />
);
