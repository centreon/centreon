/* eslint-disable no-alert */

import React from 'react';

import Button from '.';

export default { title: 'Button' };

export const regularOrange = () => (
  <Button
    buttonType="regular"
    color="orange"
    label="Button Regular"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularBlue = () => (
  <Button
    buttonType="regular"
    color="blue"
    label="Button Regular"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularGreen = () => (
  <Button
    buttonType="regular"
    color="green"
    label="Button Regular"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularRed = () => (
  <Button
    buttonType="regular"
    color="red"
    label="Button Regular"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const regularGray = () => (
  <Button
    buttonType="regular"
    color="gray"
    label="Button Regular"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedOrange = () => (
  <Button
    buttonType="bordered"
    color="orange"
    label="Button Bordered"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedBlue = () => (
  <Button
    buttonType="bordered"
    color="blue"
    label="Button Bordered"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedGreen = () => (
  <Button
    buttonType="bordered"
    color="green"
    label="Button Bordered"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedRed = () => (
  <Button
    buttonType="bordered"
    color="red"
    label="Button Bordered"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedGray = () => (
  <Button
    buttonType="bordered"
    color="gray"
    label="Button Bordered"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const borderedBlack = () => (
  <Button
    buttonType="bordered"
    color="black"
    label="Button Bordered"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateBlue = () => (
  <Button
    buttonType="validate"
    color="blue"
    label="Button Validate"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateRed = () => (
  <Button
    buttonType="bordered"
    color="red"
    label="Button Validate"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const validateGreen = () => (
  <Button
    buttonType="validate"
    color="green"
    label="Button Validate"
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
    buttonType="regular"
    color="orange"
    iconActionType="update"
    iconColor="white"
    label="Button with icon"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);

export const iconGreenUpdate = () => (
  <Button
    buttonType="regular"
    color="green"
    iconActionType="update"
    iconColor="white"
    label="Button with icon"
    onClick={() => {
      alert('Button clicked');
    }}
  />
);
