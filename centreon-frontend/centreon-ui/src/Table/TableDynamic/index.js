/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prefer-stateless-function */
/* eslint-disable import/no-named-as-default */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './table-dynamic.scss';
import InputFieldSelect from '../../InputField/InputFieldSelect';
import Checkbox from '../../Checkbox';
import SearchLive from '../../Search/SearchLive';
import ScrollBar from '../../ScrollBar';

class TableDynamic extends Component {
  render() {
    return (
      <table className={classnames(styles['table-dynamic'])}>
        <thead>
          <tr>
            <th scope={classnames(styles.col)}>
              <div className={classnames(styles.container__row)}>
                <div
                  className={classnames(
                    styles['container__col-md-3'],
                    styles['center-vertical'],
                    styles['ml-1'],
                  )}
                >
                  <Checkbox
                    label="ALL HOSTS"
                    name="all-hosts"
                    iconColor="white"
                  />
                </div>
                <div
                  className={classnames(
                    styles['container__col-md-6'],
                    styles['center-vertical'],
                  )}
                >
                  <SearchLive />
                </div>
              </div>
            </th>
            <th scope="col">
              <InputFieldSelect customClass="medium" />
            </th>
          </tr>
        </thead>
        <tbody>
          <ScrollBar scrollType="big">
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
          </ScrollBar>
        </tbody>
      </table>
    );
  }
}

export default TableDynamic;
