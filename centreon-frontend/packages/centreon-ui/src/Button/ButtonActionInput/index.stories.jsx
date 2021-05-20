import React from 'react';

import ButtonActionInput from '.';

export default { title: 'Button/ActionInput' };

export const greenArrowRight = () => (
  <ButtonActionInput
    buttonActionType="delete"
    buttonColor="green"
    buttonIconType="arrow-right"
    iconColor="white"
  />
);
