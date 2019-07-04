import React from 'react';
import { storiesOf } from '@storybook/react';
import classnames from 'classnames';
import ButtonCustom from '@material-ui/core/Button';
import styles from '../src/Popup/popup.scss';
import styles2 from '../src/Popup/PopupNew/popup.scss';
import IconCloseNew from '../src/MaterialComponents/Icons/IconClose';
import {
  Popup,
  PopupNew,
  IconClose,
  Slider,
  MessageInfo,
  Title,
  Subtitle,
  Button,
  HorizontalLine,
  Description,
  MessageError,
  LoaderContent,
  ScrollBar,
  ProgressBar,
  ProgressBarItem,
  RadioButton,
  InputField,
  MessageStatus,
  ConfirmationDialog,
  PromptDialog,
  MassiveChangeDialog,
} from '../src';

storiesOf('Popup', module).add(
  'Popup - small',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'])}>
        <h3>Popup Header</h3>
      </div>
      <div className={classnames(styles['popup-body'])}>
        <p>Popup body</p>
      </div>
      <div className={classnames(styles['popup-footer'])}>
        <p>Popup footer</p>
      </div>
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Popup - small with header and footer colored (with message status)',
  () => (
    <Popup popupType="small" customClass="scroll">
      <div className={classnames(styles['popup-header'], styles.blue)}>
        <h3 className={classnames(styles['popup-header-title'])}>
          Popup Header
        </h3>
      </div>
      <div className={classnames(styles['popup-body'])}>
        <br />
        <br />
        <LoaderContent />
        <br />
        <br />
        <MessageStatus
          messageStatus="ok"
          messageText="Insertion 4/4 hosts"
          messageInfo="[OK]"
        />
        <br />
        <MessageStatus
          messageStatus="failed"
          messageText="Generation of configuration"
          messageInfo="[FAILED]"
        />
      </div>
      <div className={classnames(styles['popup-footer'])} />
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Popup - small with header and footer colored (with message status error)',
  () => (
    <Popup popupType="small" customClass="scroll host">
      <div className={classnames(styles['popup-header'], styles.blue)}>
        <h3 className={classnames(styles['popup-header-title'])}>
          Popup Header
        </h3>
      </div>
      <div className={classnames(styles['popup-body'])}>
        <br />
        <br />
        <LoaderContent />
        <br />
        <br />
        <MessageStatus
          messageStatus="ok"
          messageText="Insertion 4/4 hosts"
          messageInfo="[OK]"
        />
        <br />
        <MessageStatus
          messageStatus="failed"
          messageText="Generation of configuration"
          messageInfo="[FAILED]"
        />
      </div>
      <div className={classnames(styles['popup-footer'], styles['m-0'])}>
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

storiesOf('Popup', module).add(
  'Popup - with scroll',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'], styles.blue)}>
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
        </div>
      </ScrollBar>
      <div className={classnames(styles['popup-footer'], styles['m-0'])}>
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

storiesOf('Popup', module).add(
  'Popup - dynamic list of sources',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'], styles['light-blue'])}>
        <ProgressBar customStyles="progress-bar-custom-styles">
          <ProgressBarItem classActive="active" number="1" />
          <ProgressBarItem number="2" />
        </ProgressBar>
      </div>
      <ScrollBar>
        <div className={classnames(styles['popup-body'])}>
          <Title titleColor="host" label="Resource Discover Wizard" />
          <Subtitle label="Choose a discovery source:" />
          <br />
          <RadioButton
            checked
            label="WMware infrastructure servers"
            name="test"
          />
          <RadioButton label="WMware VM" name="test2" />
          <RadioButton label="Microsoft Azure VM" name="test3" />
          <RadioButton label="Azure EC2" name="test4" />
        </div>
      </ScrollBar>
      <div
        className={classnames(styles['popup-footer'], styles['text-center'])}
      >
        <Button
          label="CANCEL"
          buttonType="validate"
          color="red"
          customClass="normal"
          customSecond="mr-2"
        />
        <Button
          label="NEXT"
          buttonType="validate"
          color="blue"
          customClass="normal"
        />
      </div>
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Popup - dynamic list of sources form',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'], styles['light-blue'])}>
        <ProgressBar customStyles="progress-bar-custom-styles">
          <ProgressBarItem classActive="active" number="1" />
          <ProgressBarItem number="2" />
        </ProgressBar>
      </div>
      <ScrollBar>
        <div className={classnames(styles['popup-body'])}>
          <Title titleColor="host" label="Resource Discover Wizard" />
          <br />
          <InputField
            type="text"
            name="test1"
            inputSize="big"
            label="Login Endpoint"
          />
          <InputField
            type="number"
            name="test2"
            inputSize="big"
            label="Talend ID"
          />
          <InputField
            type="number"
            name="test3"
            inputSize="big"
            label="Client ID"
          />
          <InputField
            type="password"
            name="test4"
            inputSize="big"
            label="Client Secret"
          />
          <InputField
            type="number"
            name="test5"
            inputSize="big"
            label="Subscription:"
          />
          <InputField
            type="text"
            name="test5"
            inputSize="big"
            label="Management Endpoint"
          />
        </div>
      </ScrollBar>
      <div className={classnames(styles['popup-footer'])}>
        <Button
          label="BACK"
          buttonType="validate"
          color="blue"
          customClass="normal"
        />
        <div className={classnames(styles['f-r'])}>
          <Button
            label="START"
            buttonType="validate"
            color="blue"
            customClass="normal"
          />
        </div>
      </div>
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Popup - dynamic list of sources form (with loader)',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'], styles.blue)}>
        <ProgressBar customStyles="progress-bar-custom-styles">
          <ProgressBarItem classActive="active" number="1" />
          <ProgressBarItem number="2" />
          <ProgressBarItem number="3" />
        </ProgressBar>
      </div>
      <ScrollBar>
        <div className={classnames(styles['popup-body'])}>
          <Title titleColor="host" label="Resource Discover Wizard" />
          <br />
          <InputField
            type="text"
            name="test1"
            inputSize="big"
            label="Login Endpoint"
          />
          <InputField
            type="number"
            name="test2"
            inputSize="big"
            label="Talend ID"
          />
          <InputField
            type="number"
            name="test3"
            inputSize="big"
            label="Client ID"
          />
          <InputField
            type="password"
            name="test4"
            inputSize="big"
            label="Client Secret"
          />
          <InputField
            type="number"
            name="test5"
            inputSize="big"
            label="Subscription:"
          />
          <InputField
            type="text"
            name="test5"
            inputSize="big"
            label="Management Endpoint"
          />
        </div>
      </ScrollBar>
      <div className={classnames(styles['popup-footer'])}>
        <Button
          label="BACK"
          buttonType="validate"
          color="blue"
          customClass="normal"
        />
        <div className={classnames(styles['f-r'])}>
          <LoaderContent />
        </div>
      </div>
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Popup - dynamic list of sources form (with error)',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'], styles.blue)}>
        <ProgressBar customStyles="progress-bar-custom-styles">
          <ProgressBarItem classActive="active" number="1" />
          <ProgressBarItem number="2" />
        </ProgressBar>
      </div>
      <ScrollBar>
        <div className={classnames(styles['popup-body'])}>
          <Title titleColor="host" label="Resource Discover Wizard" />
          <br />
          <InputField
            type="text"
            name="test1"
            inputSize="big"
            label="Login Endpoint"
          />
          <InputField
            type="number"
            name="test2"
            inputSize="big"
            label="Talend ID"
          />
          <InputField
            type="number"
            name="test3"
            inputSize="big"
            label="Client ID"
          />
          <InputField
            type="password"
            name="test4"
            inputSize="big"
            label="Client Secret"
          />
          <InputField
            type="number"
            name="test5"
            inputSize="big"
            label="Subscription:"
          />
          <InputField
            type="text"
            name="test5"
            inputSize="big"
            label="Management Endpoint"
          />
        </div>
      </ScrollBar>
      <div className={classnames(styles['popup-footer'])}>
        <div>
          <Button
            label="BACK"
            buttonType="validate"
            color="blue"
            customClass="normal"
          />
          <div className={classnames(styles['f-r'])}>
            <Button
              label="START"
              buttonType="validate"
              color="blue"
              customClass="normal"
            />
          </div>
        </div>
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

storiesOf('Popup', module).add(
  'Popup - big',
  () => (
    <Popup popupType="big">
      <div className={classnames(styles['popup-header'])}>
        <h3>Popup Header</h3>
      </div>
      <div className={classnames(styles['popup-body'])}>
        <p>Popup body</p>
      </div>
      <div className={classnames(styles['popup-footer'])}>
        <p>Popup footer</p>
      </div>
      <IconClose iconPosition="icon-close-position-big" iconType="big" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup Extensions Info', module).add(
  'Popup - big',
  () => (
    <Popup popupType="big">
      <Slider
        images={[
          'https://res.cloudinary.com/dezez0fsn/image/upload/v1549874437/slider-default-image.png',
          'https://static.centreon.com/wp-content/uploads/2018/09/plugin-banner-it-operatio' +
            'ns-management.png',
          'https://s3.us-east-2.amazonaws.com/dzuz14/thumbnails/canyon.jpg',
          'https://s3.us-east-2.amazonaws.com/dzuz14/thumbnails/city.jpg',
          'https://s3.us-east-2.amazonaws.com/dzuz14/thumbnails/desert.jpg',
        ]}
      />
      <div className={classnames(styles['popup-header'])}>
        <Title label="Nom de Module" />
        <Subtitle label="by Centreon" />
        <Button label="Available 3.1.5" buttonType="regular" color="blue" />
        <Button
          label="Stable"
          buttonType="bordered"
          color="gray"
          style={{ margin: '15px' }}
        />
        <Button
          label="Expire 12/08/2018"
          buttonType="bordered"
          color="orange"
        />
      </div>
      <HorizontalLine />
      <div className={classnames(styles['popup-body'])}>
        <Description date="Last update 12/7/2018" />
        <Description title="Description:" />
        <Description text="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum." />
        <Description text="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum." />
        <Description text="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum." />
      </div>
      <HorizontalLine />
      <div className={classnames(styles['popup-footer'])}>
        <Description note="Release note of v 3.11.5 available here >" />
      </div>
      <IconClose iconPosition="icon-close-position-big" iconType="big" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup Extensions Delete', module).add(
  'Popup - small',
  () => (
    <Popup popupType="small">
      <div className={classnames(styles['popup-header'])}>
        <Title label="Engine-status" icon="object" />
      </div>
      <div className={classnames(styles['popup-body'])}>
        <MessageInfo
          messageInfo="red"
          text="Do you want to delete this extension. This, action will remove all associated data."
        />
      </div>
      <div className={classnames(styles['popup-footer'])}>
        <div className={classnames(styles.container__row)}>
          <div className={classnames(styles['container__col-xs-6'])}>
            <Button
              label="Delete"
              buttonType="regular"
              color="red"
              iconActionType="delete-white"
            />
          </div>
          <div
            className={classnames(
              styles['container__col-xs-6'],
              styles['text-right'],
            )}
          >
            <Button label="Cancel" buttonType="regular" color="gray" />
          </div>
        </div>
      </div>
      <IconClose iconPosition="icon-close-position-small" iconType="middle" />
    </Popup>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Popup - new',
  () => (
    <PopupNew popupType="small">
      <div className={classnames(styles2['popup-header'])}>
        <h3 className={classnames(styles2['popup-title'])}>Warning</h3>
      </div>
      <div className={classnames(styles2['popup-body'])}>
        <p className={classnames(styles2['popup-info'])}>
          Mandatory fields are not filled!
        </p>
        <ButtonCustom
          variant="contained"
          color="primary"
          style={{
            backgroundColor: '#0072CE',
            fontSize: 11,
            textAlign: 'center',
            padding: '5px 25px',
          }}
        >
          COMPLETE
        </ButtonCustom>
      </div>
      <IconCloseNew />
    </PopupNew>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Popup', module).add(
  'Dialog - Confirmation',
  () => (
    <ConfirmationDialog active info="Delete selected business activities?" />
  ),
  { notes: 'Confirmation dialog component' },
);

storiesOf('Popup', module).add(
  'Dialog - Promt',
  () => (
    <PromptDialog
      active
      info="How many times would you like to duplicate selected BAs?"
    />
  ),
  { notes: 'Promt dialog with input' },
);

storiesOf('Popup', module).add(
  'Dialog - Massive change',
  () => (
    <MassiveChangeDialog
      active
      header="Massive calculation method change"
      info="Input value of critical and warning threshold for selected BAs"
    />
  ),
  { notes: 'Massive change dialog with two inputs' },
);

storiesOf('Popup', module).add(
  'Popup - new validate',
  () => (
    <PopupNew popupType="small">
      <div className={classnames(styles2['popup-header'])}>
        <h3 className={classnames(styles2['popup-title'])}>
          Changes have been made
        </h3>
      </div>
      <div className={classnames(styles2['popup-body'])}>
        <p className={classnames(styles2['popup-info'])}>
          Would you like to save before closing?
        </p>
        <ButtonCustom
          variant="contained"
          color="primary"
          style={{
            backgroundColor: '#0072CE',
            fontSize: 11,
            textAlign: 'center',
            border: '1px solid #0072CE',
          }}
        >
          SAVE
        </ButtonCustom>
        <ButtonCustom
          variant="contained"
          color="primary"
          style={{
            backgroundColor: '#0072CE',
            fontSize: 11,
            textAlign: 'center',
            marginLeft: 30,
            backgroundColor: 'transparent',
            color: '#0072CE',
            border: '1px solid #0072CE',
            boxSizing: 'border-box',
          }}
        >
          DON'T SAVE
        </ButtonCustom>
      </div>
      <IconCloseNew />
    </PopupNew>
  ),
  { notes: 'A very simple component' },
);
