import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';
import { Form } from '@centreon/ui';

import { playlistConfigInitialValuesAtom } from '../atoms';
import { PlaylistConfig } from '../models';
import { labelPlaylistProperties } from '../../../translatedLabels';

import { inputs } from './inputs';
import { getValidationSchema } from './validationSchema';
import FormActions from './FormActions';

const PlaylistConfig = (): JSX.Element => {
  const { t } = useTranslation();

  const playlistConfigInitialValues = useAtomValue(
    playlistConfigInitialValuesAtom
  );

  const isOpen = !!playlistConfigInitialValues;

  const submit = (): void => console.log('yeah');

  return (
    <Modal hasCloseButton={false} open={isOpen}>
      <Modal.Header>{t(labelPlaylistProperties)}</Modal.Header>
      <Modal.Body>
        <Form<PlaylistConfig>
          Buttons={FormActions}
          initialValues={playlistConfigInitialValues as PlaylistConfig}
          inputs={inputs}
          submit={submit}
          validationSchema={getValidationSchema(t)}
        />
      </Modal.Body>
    </Modal>
  );
};

export default PlaylistConfig;
