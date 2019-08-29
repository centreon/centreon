/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import {
  IconAction,
  IconClose,
  IconCloseNew,
  IconContent,
  IconInfo,
  IconHeader,
  IconNumber,
  IconRound,
  IconToggleSubmenu,
  IconLegend,
  IconLibraryAdd,
  IconDelete,
  IconAttach,
  IconEdit,
  IconInsertChart,
  IconPowerSettings,
  IconPowerSettingsDisable,
  IconVisible,
  IconInvisible,
  IconError,
} from '../src';

storiesOf('Icon', module).add(
  'Icon - action',
  () => {
    return (
      <React.Fragment>
        <IconAction iconActionType="update" />
        <IconAction iconActionType="clock" />
        <IconAction iconActionType="check" />
        <IconAction iconActionType="warning" />
        <IconAction iconActionType="arrow-right" />
      </React.Fragment>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - close small',
  () => <IconClose iconType="small" />,
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - close middle',
  () => <IconClose iconType="middle" />,
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - close big',
  () => <IconClose iconType="big" />,
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - content',
  () => <IconContent iconContentType="add" iconContentColor="green" />,
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - header',
  () => {
    return (
      <div
        style={{
          backgroundColor: '#232f39',
          padding: '10px',
        }}
      >
        <IconHeader
          iconType="poller"
          style={{
            display: 'inline-block',
          }}
        />
        <IconHeader
          iconType="hosts"
          style={{
            display: 'inline-block',
          }}
        />
        <IconHeader
          iconType="services"
          style={{
            display: 'inline-block',
          }}
        />
        <IconHeader
          iconType="user"
          style={{
            display: 'inline-block',
          }}
        />
        <IconHeader
          iconType="top-counter"
          style={{
            display: 'inline-block',
          }}
        />
      </div>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - info',
  () => {
    return (
      <React.Fragment>
        <IconInfo iconName="state" />
        <IconInfo iconName="question" />
      </React.Fragment>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - info with text',
  () => {
    return (
      <React.Fragment>
        <IconInfo iconName="question" iconText="Test" />
      </React.Fragment>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - number bordered',
  () => {
    return (
      <div
        style={{
          backgroundColor: '#232f39',
          padding: '10px',
        }}
      >
        <IconNumber iconType="bordered" iconColor="red" iconNumber="3" />
      </div>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - number colored',
  () => {
    return (
      <div
        style={{
          backgroundColor: '#232f39',
          padding: '10px',
        }}
      >
        <IconNumber iconType="colored" iconColor="green" iconNumber="10" />
      </div>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - round colored',
  () => {
    return (
      <div
        style={{
          backgroundColor: '#232f39',
          padding: '10px',
        }}
      >
        <IconRound
          iconType="database"
          iconColor="green"
          iconPosition="icon-round-position"
          iconTitle="OK: all database poller updates are active"
        />
        <IconRound
          iconType="clock"
          iconColor="green"
          iconPosition="icon-round-position"
          iconTitle="OK: all database poller updates are active"
        />
      </div>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - toggle',
  () => {
    return (
      <div
        style={{
          backgroundColor: '#232f39',
          padding: '10px',
        }}
      >
        <IconToggleSubmenu iconType="arrow" />
      </div>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - legend',
  () => {
    return (
      <React.Fragment>
        <IconLegend
          iconColor="gray"
          buttonActionType="clock"
          buttonIconType="clock"
        />
        <IconLegend
          iconColor="red"
          buttonActionType="warning mr-2"
          buttonIconType="warning"
        />
        <IconLegend
          iconColor="green"
          buttonActionType="check"
          buttonIconType="check"
        />
      </React.Fragment>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add(
  'Icon - legend with title',
  () => {
    return (
      <React.Fragment>
        <IconLegend
          iconColor="gray"
          buttonActionType="clock"
          buttonIconType="clock"
          title="runing"
          legendType="title"
        />
        <IconLegend
          iconColor="red"
          buttonActionType="warning"
          buttonIconType="warning"
          title="failed"
          legendType="title"
        />
        <IconLegend
          iconColor="green"
          buttonActionType="check"
          buttonIconType="check"
          title="finished"
          legendType="title"
        />
      </React.Fragment>
    );
  },
  { notes: 'A very simple component' },
);

storiesOf('Icon', module).add('Icon - Material', () => {
  return (
    <>
      <IconDelete />
      <IconEdit />
      <IconCloseNew />
      <IconLibraryAdd />
      <IconPowerSettings />
      <IconPowerSettingsDisable />
      <IconAttach />
      <IconInsertChart />
      <IconVisible />
      <IconInvisible />
      <IconError />
    </>
  );
});
