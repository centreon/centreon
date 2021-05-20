import * as React from 'react';

import ExtensionDetailsPopup from '.';

export default { title: 'Extension Details Popup' };

const noOp = () => undefined;

const modalDetails = {
  description: 'My module description',
  id: 0,
  last_update: '01/01/1970',
  release_note: 'the release note',
  stability: 'Stable',
  title: 'My module',
  type: 'module',
  version: {
    available: '2.0.0',
    installed: true,
    outdated: false,
  },
};

const commonProps = {
  animate: false,
  modalDetails,
  onCloseClicked: noOp,
  onDeleteClicked: noOp,
  onInstallClicked: noOp,
  onUpdateClicked: noOp,
};

export const normal = (): JSX.Element => (
  <ExtensionDetailsPopup type="module" {...commonProps} />
);

export const normalWithLoading = (): JSX.Element => (
  <ExtensionDetailsPopup loading type="module" {...commonProps} />
);
