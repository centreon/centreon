/* eslint-disable no-alert */
import React from 'react';

import classnames from 'classnames';

import Card from '.';
import CardItem from './CardItem';
import Subtitle from '../Subtitle';
import IconInfo from '../Icon/IconInfo';
import Title from '../Title';
import styles from './card.scss';
import Button from '../Button';
import ButtonAction from '../Button/ButtonAction';

export default { title: 'Card' };

export const withoutContent = () => (
  <Card>
    <CardItem
      itemBorderColor="orange"
      itemFooterColor="orange"
      itemFooterLabel="Some label for the footer"
      style={{
        width: '250px',
      }}
    />
  </Card>
);

export const withContent = () => (
  <Card>
    <CardItem
      itemBorderColor="orange"
      itemFooterColor="orange"
      itemFooterLabel="Some label for the footer"
      style={{
        width: '250px',
      }}
      onClick={() => {
        alert('Card clicked- open popin');
      }}
    >
      <IconInfo
        iconName="state"
        iconColor="green"
        iconPosition="info-icon-position"
      />
      <div className={classnames(styles['custom-title-heading'])}>
        <Title
          icon="object"
          label="Test Title"
          customTitleStyles="custom-title-styles"
          onClick={() => {
            alert('Card clicked- open popin');
          }}
        />
        <Subtitle
          label="Test Subtitle"
          customSubtitleStyles="custom-subtitle-styles"
          onClick={() => {
            alert('Card clicked- open popin');
          }}
        />
      </div>
      <Button
        buttonType="regular"
        color="orange"
        label="Button example"
        iconActionType="update"
        iconColor="white"
        iconPosition="icon-right"
        position="button-card-position"
        onClick={() => {
          alert('Button clicked');
        }}
      />
      <ButtonAction
        iconColor="gray"
        buttonActionType="delete"
        buttonIconType="delete"
        iconPosition="icon-right"
        customPosition="button-action-card-position"
        onClick={() => {
          alert('Button delete clicked');
        }}
      />
    </CardItem>
  </Card>
);
