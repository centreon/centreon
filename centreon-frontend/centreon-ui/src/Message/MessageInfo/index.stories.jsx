import React from 'react';

import MessageInfo from '.';

export default { title: 'MessageInfo' };

export const red = () => (
  <MessageInfo
    messageInfo="red"
    text="Do you want to delete this extension. This, action will remove all associated data."
  />
);
