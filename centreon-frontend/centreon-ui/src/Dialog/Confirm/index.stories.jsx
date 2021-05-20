import React from 'react';

import ConfirmDialog from '.';

export default { title: 'Dialog/Confirm' };

export const normal = () => (
  <ConfirmDialog
    open
    labelMessage="Your progress will not be saved."
    labelTitle="Do you want to confirm action ?"
    onCancel={() => {}}
  />
);
