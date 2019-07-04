import React from 'react';
import { storiesOf } from '@storybook/react';
import { MessageInfo, MessageError, MessageStatus } from '../src';

storiesOf('Message', module).add(
  'Message info - red',
  () => (
    <MessageInfo
      messageInfo="red"
      text="Do you want to delete this extension. This, action will remove all associated data."
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Message', module).add(
  'Message error - red',
  () => (
    <MessageError
      messageError="red"
      text="Generation of configuration has failed, please try again."
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Message', module).add(
  'Message status',
  () => (
    <React.Fragment>
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
    </React.Fragment>
  ),
  { notes: 'A very simple component' },
);
