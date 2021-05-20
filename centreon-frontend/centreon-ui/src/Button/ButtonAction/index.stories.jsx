/* eslint-disable no-alert */

import React from 'react';

import ButtonAction from '.';

export default { title: 'Button/Action' };

export const deleteAction = () => (
  <ButtonAction
    buttonActionType="delete"
    buttonIconType="delete"
    iconColor="gray"
    onClick={() => {
      alert("I've been clicked");
    }}
  />
);
