/* eslint-disable no-alert */

import React from 'react';

import Button from '.';

export default { title: 'Button' };

export const regularOrange = () => (
  <Button
    label="Button Regular"
    buttonType="regular"
    color="orange"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularBlue = () => (
  <Button
    label="Button Regular"
    buttonType="regular"
    color="blue"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularGreen = () => (
  <Button
    label="Button Regular"
    buttonType="regular"
    color="green"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularRed = () => (
  <Button
    label="Button Regular"
    buttonType="regular"
    color="red"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularGray = () => (
  <Button
    label="Button Regular"
    buttonType="regular"
    color="gray"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedOrange = () => (
  <Button
    label="Button Bordered"
    buttonType="bordered"
    color="orange"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedBlue = () => (
  <Button
    label="Button Bordered"
    buttonType="bordered"
    color="blue"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedGreen = () => (
  <Button
    label="Button Bordered"
    buttonType="bordered"
    color="green"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedRed = () => (
  <Button
    label="Button Bordered"
    buttonType="bordered"
    color="red"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedGray = () => (
  <Button
    label="Button Bordered"
    buttonType="bordered"
    color="gray"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedBlack = () => (
  <Button
    label="Button Bordered"
    buttonType="bordered"
    color="black"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateBlue = () => (
  <Button
    label="Button Validate"
    buttonType="validate"
    color="blue"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateRed = () => (
  <Button
    label="Button Validate"
    buttonType="bordered"
    color="red"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateGreen = () => (
  <Button
    label="Button Validate"
    buttonType="validate"
    color="green"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateIconGreenArrowLeft = () => (
  <Button
    buttonType="validate"
    color="green"
    customSecond="icon"
    iconActionType="arrow-left"
    iconColor="white"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const iconOrangeUpdate = () => (
  <Button
    label="Button with icon"
    buttonType="regular"
    color="orange"
    iconActionType="update"
    iconColor="white"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const iconGreenUpdate = () => (
  <Button
    label="Button with icon"
    buttonType="regular"
    color="green"
    iconActionType="update"
    iconColor="white"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);
