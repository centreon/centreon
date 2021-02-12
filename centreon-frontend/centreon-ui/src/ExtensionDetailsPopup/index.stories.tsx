import * as React from 'react';

import ExtensionDetailsPopup from '.';

export default { title: 'Extension Details Popup' };

const noOp = () => undefined;

const modalDetails = {
  id: 0,
  type: 'module',
  title: 'My module',
  description: 'My module description',
  version: {
    installed: true,
    outdated: false,
    available: '2.0.0',
  },
  stability: 'Stable',
  last_update: '01/01/1970',
  release_note: 'the release note',
};

const commonProps = {
  modalDetails,
  onCloseClicked: noOp,
  onDeleteClicked: noOp,
  onUpdateClicked: noOp,
  onInstallClicked: noOp,
  animate: false,
};

export const normal = (): JSX.Element => (
  <ExtensionDetailsPopup type="module" {...commonProps} />
);

export const normalWithLoading = (): JSX.Element => (
  <ExtensionDetailsPopup type="module" loading {...commonProps} />
);
