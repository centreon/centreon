import React, {Component} from 'react';
import classnames from 'classnames';
import styles from './list-sortable.scss';
import Checkbox from '../../Checkbox';
import InputFieldMultiSelect from '../../InputField/InputFieldMultiSelect';
import { SwitcherMode } from '../..';

class ListSortable extends Component {
  render() {
    return (
      <table className={classnames(styles.list, styles["list-sortable"])}>
        <thead>
          <tr>
            <th scope="col">INDICATORS</th>
            <th scope="col">TYPE</th>
            <th scope="col">DEFINE IMPACT</th>
            <th scope="col">WARNING</th>
            <th scope="col">CRITICAL</th>
            <th scope="col">UNKOWN</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <Checkbox label="Lorem Ipsum dolor sit amet" name="all-hosts" iconColor="light-blue" />
            </td>
            <td>
              Type 1
            </td>
            <td>
              <SwitcherMode />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox label="Lorem Ipsum dolor sit amet" name="all-hosts" iconColor="light-blue" />
            </td>
            <td>
              Type 2
            </td>
            <td>
              <SwitcherMode />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox label="Lorem Ipsum dolor sit amet" name="all-hosts" iconColor="light-blue" />
            </td>
            <td>
              Type 3
            </td>
            <td>
              <SwitcherMode />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
            <td>
              <InputFieldMultiSelect size="small" />
            </td>
          </tr>
        </tbody>
      </table>
    );
  }
}

export default ListSortable;