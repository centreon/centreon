import React from 'react';
import { storiesOf } from '@storybook/react';
import classnames from 'classnames';
import styles from '../src/Popup/popup.scss';
import { ScrollBar, Popup, MessageError, IconClose } from '../src';

storiesOf('Scroll', module).add(
  'Scrollbar - custom',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'], styles['light-blue'])}>
        <h3 className={classnames(styles['popup-header-title'])}>
          Popup Header
        </h3>
      </div>
      <ScrollBar>
        <div className={classnames(styles['popup-body'])}>
          <p className={classnames(styles['description-text'])}>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam
            lobortis faucibus tellus. Phasellus in felis sed elit hendrerit
            facilisis eget sollicitudin ante. Mauris suscipit porttitor semper.
            Aenean laoreet risus diam, in aliquam ante laoreet in. Nulla mollis
            velit dolor, vitae sagittis eros auctor in. Phasellus id tincidunt
            lacus, et elementum eros. Phasellus id commodo risus. Quisque
            sagittis cursus eros et ornare. Aenean at magna arcu. Curabitur
            fringilla eu quam et aliquet. Nam sed libero semper, pellentesque
            justo sit amet, tempus sapien. Donec viverra nisi at sapien semper
            hendrerit. Nunc sed fermentum dolor, at varius leo. Donec
            ullamcorper dui at tincidunt facilisis. Praesent a pretium nisi.
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
          </p>
          <p className={classnames(styles['description-text'])}>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam
            lobortis faucibus tellus. Phasellus in felis sed elit hendrerit
            facilisis eget sollicitudin ante. Mauris suscipit porttitor semper.
            Aenean laoreet risus diam, in aliquam ante laoreet in. Nulla mollis
            velit dolor, vitae sagittis eros auctor in. Phasellus id tincidunt
            lacus, et elementum eros. Phasellus id commodo risus. Quisque
            sagittis cursus eros et ornare. Aenean at magna arcu. Curabitur
            fringilla eu quam et aliquet. Nam sed libero semper, pellentesque
            justo sit amet, tempus sapien. Donec viverra nisi at sapien semper
            hendrerit. Nunc sed fermentum dolor, at varius leo. Donec
            ullamcorper dui at tincidunt facilisis. Praesent a pretium nisi.
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
          </p>
        </div>
      </ScrollBar>
      <div className={classnames(styles['popup-footer'])}>
        <MessageError
          messageError="red"
          text="Generation of configuration has failed, please try again."
          messageErrorPosition="message-error-popup-position"
        />
      </div>
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);
