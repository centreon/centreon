/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import classnames from 'classnames';
import styles from '../src/global-sass-files/_grid.scss';
import { TopFilters } from '../src';

storiesOf('Top Filters', module).add(
  'Top Fliters Example',
  () => (
    <TopFilters
      fullText={{
        label: 'Search:',
        onChange: (a) => {
          console.log(a);
        },
      }}
      switchers={[
        [
          {
            customClass: classnames(
              styles['container__col-md-3'],
              styles['container__col-xs-4'],
            ),
            switcherTitle: 'Status:',
            switcherStatus: 'Not installed',
            defaultValue: false,
            onChange: (value) => {
              console.log(value);
            },
          },
          {
            customClass: classnames(
              styles['container__col-md-3'],
              styles['container__col-xs-4'],
            ),
            switcherStatus: 'Installed',
            defaultValue: false,
            onChange: (value) => {
              console.log(value);
            },
          },
          {
            customClass: classnames(
              styles['container__col-md-3'],
              styles['container__col-xs-4'],
            ),
            switcherStatus: 'Update',
            defaultValue: false,
            onChange: (value) => {
              console.log(value);
            },
          },
        ],
        [
          {
            customClass: classnames(
              styles['container__col-sm-3'],
              styles['container__col-xs-4'],
            ),
            switcherTitle: 'Type:',
            switcherStatus: 'Module',
            defaultValue: false,
            onChange: (value) => {
              console.log(value);
            },
          },
          {
            customClass: classnames(
              styles['container__col-sm-3'],
              styles['container__col-xs-4'],
            ),
            switcherStatus: 'Update',
            defaultValue: false,
            onChange: (value) => {
              console.log(value);
            },
          },
          {
            button: true,
            label: 'Clear Filters',
            color: 'black',
            buttonType: 'bordered',
            onClick: () => {
              console.log('Clear filters clicked');
            },
          },
        ],
      ]}
    />
  ),
  {
    notes: 'Filters with full text',
  },
);
